<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useCalendarStore, type AcademicEvent } from '../../../store/calendarStore'
import { useStudentProfile } from '../composables/useStudentProfile'
import { useStudentExamStore } from '../store/studentExamStore'

import Header from '../components/Header.vue'
import HeroSection from '../components/HeroSection.vue'
import StudentSidebar from '../components/StudentSidebar.vue'
import ProfileModal from '../components/ProfileModal.vue'

import {
  Calendar,
  CalendarDays,
  CalendarCheck,
  Clock,
  ChevronLeft,
  ChevronRight,
  RotateCcw,
  Search,
  Filter,
  Sparkles,
  Printer,
  Layers,
  BookOpen,
  GraduationCap,
  PartyPopper,
  AlertCircle,
  CheckCircle2,
  MapPin,
  Info,
  List,
  Grid,
  X,
  Eye,
  Tag,
  ChevronDown,
} from 'lucide-vue-next'

const calendarStore = useCalendarStore()
const { profile, fetchProfile } = useStudentProfile()
const examStore = useStudentExamStore()

// ── Modals & Drawer State ────────────────────────────────
const isSidebarOpen = ref(false)
const isProfileOpen = ref(false)
const selectedEventForModal = ref<AcademicEvent | null>(null)
const isRefreshing = ref(false)

// ── View & Date State ────────────────────────────────────
const today = new Date()
const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`

const calendarYear = ref(today.getFullYear())
const calendarMonth = ref(today.getMonth() + 1) // 1-based (1-12)
const selectedDateStr = ref(todayStr)
const viewMode = ref<'month' | 'week' | 'list'>('month')

// ── Filter State ─────────────────────────────────────────
const eventSearch = ref('')
const selectedCategoryFilter = ref<string>('all')
const selectedStatusFilter = ref<string>('all')

const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

// ── Computed Properties ──────────────────────────────────
const calendarTitle = computed(() => {
  const d = new Date(calendarYear.value, calendarMonth.value - 1, 1)
  return d.toLocaleString('default', { month: 'long', year: 'numeric' })
})

const results = computed(() => examStore.results)
const upcomingExams = computed(() => examStore.upcomingExams)

// Academic period name
const currentSemesterLabel = computed(() => {
  if (calendarStore.eventStats.current_semester) {
    const year = calendarStore.eventStats.current_year || '2025/2026'
    return `${year} • ${calendarStore.eventStats.current_semester}`
  }
  return '2025 Second Semester'
})

// 4 KPI Summary Cards
const statsCards = computed(() => [
  {
    label: 'TOTAL EVENTS',
    value: String(calendarStore.eventStats.total || calendarStore.events.length || 0),
    sub: 'This Semester',
    icon: Calendar,
    color: 'bg-[#EEF0FF] text-[#4F35F3]',
    border: 'border-[#D6DBFF]',
  },
  {
    label: 'ACADEMIC WEEKS',
    value: String(calendarStore.eventStats.academic_weeks || 16),
    sub: 'Active Period',
    icon: Layers,
    color: 'bg-emerald-50 text-emerald-600',
    border: 'border-emerald-100',
  },
  {
    label: 'HOLIDAYS',
    value: String(calendarStore.eventStats.holidays || 0),
    sub: 'Scheduled Breaks',
    icon: PartyPopper,
    color: 'bg-amber-50 text-amber-600',
    border: 'border-amber-100',
  },
  {
    label: 'EXAM PERIODS',
    value: String(calendarStore.eventStats.exams || 0),
    sub: 'Scheduled Periods',
    icon: GraduationCap,
    color: 'bg-blue-50 text-blue-600',
    border: 'border-blue-100',
  },
])

// ── 42-Cell Month Grid Calculation ───────────────────────
const calendarCells = computed(() => {
  const y = calendarYear.value, m = calendarMonth.value
  const firstDay = new Date(y, m - 1, 1).getDay() // 0=Sun
  const daysInMonth = new Date(y, m, 0).getDate()
  const daysInPrev = new Date(y, m - 1, 0).getDate()
  const cells: { day: number; month: 'prev' | 'current' | 'next'; dateStr: string }[] = []

  // Trailing days from previous month
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

  // Leading days into next month
  const remaining = 42 - cells.length
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

// ── 7-Day Week View Calculation ──────────────────────────
const weekCells = computed(() => {
  const baseDate = selectedDateStr.value ? new Date(selectedDateStr.value) : new Date(today)
  const dayOfWeek = isNaN(baseDate.getTime()) ? today.getDay() : baseDate.getDay() // 0=Sun
  const sunday = new Date(baseDate)
  sunday.setDate(baseDate.getDate() - dayOfWeek)

  const weekList = []
  for (let i = 0; i < 7; i++) {
    const d = new Date(sunday)
    d.setDate(sunday.getDate() + i)
    const dateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    weekList.push({
      day: d.getDate(),
      dayName: days[i],
      monthName: d.toLocaleString('default', { month: 'short' }),
      dateStr,
      isToday: dateStr === todayStr,
      isSelected: dateStr === selectedDateStr.value,
      events: eventsOnDay(dateStr)
    })
  }
  return weekList
})

// Events for a specific day
function eventsOnDay(dateStr: string): AcademicEvent[] {
  let list = calendarStore.events.filter(e => e.start_date <= dateStr && e.end_date >= dateStr)
  if (selectedCategoryFilter.value !== 'all') {
    list = list.filter(e => {
      const cat = (e.category_name || '').toLowerCase()
      return cat === selectedCategoryFilter.value.toLowerCase() ||
             String(e.category_id) === selectedCategoryFilter.value
    })
  }
  return list
}

// Events for the selected date
const selectedDateEvents = computed(() => {
  if (!selectedDateStr.value) return []
  return eventsOnDay(selectedDateStr.value)
})

// Formatted selected date string
const formattedSelectedDate = computed(() => {
  if (!selectedDateStr.value) return ''
  try {
    const [y, m, d] = selectedDateStr.value.split('-').map(Number)
    const dateObj = new Date(y, m - 1, d)
    return dateObj.toLocaleDateString('en-US', {
      weekday: 'long',
      month: 'long',
      day: 'numeric',
      year: 'numeric'
    })
  } catch {
    return selectedDateStr.value
  }
})

// Filtered events for the table and agenda
const filteredEvents = computed(() => {
  let list = calendarStore.events || []

  // Category filter
  if (selectedCategoryFilter.value !== 'all') {
    list = list.filter(e => {
      const cat = (e.category_name || '').toLowerCase()
      return cat === selectedCategoryFilter.value.toLowerCase() ||
             String(e.category_id) === selectedCategoryFilter.value
    })
  }

  // Status filter
  if (selectedStatusFilter.value !== 'all') {
    list = list.filter(e => e.status === selectedStatusFilter.value)
  }

  // Search filter
  if (eventSearch.value.trim()) {
    const q = eventSearch.value.trim().toLowerCase()
    list = list.filter(e =>
      e.title.toLowerCase().includes(q) ||
      (e.category_name ?? '').toLowerCase().includes(q) ||
      (e.description ?? '').toLowerCase().includes(q) ||
      e.start_date.includes(q) ||
      e.end_date.includes(q)
    )
  }

  return list
})

// ── Visual Helper Functions ──────────────────────────────
function getCategoryTheme(event: AcademicEvent) {
  const cat = (event.category_name || '').toLowerCase()
  const title = (event.title || '').toLowerCase()

  if (cat.includes('exam') || title.includes('exam') || title.includes('quiz') || title.includes('midterm') || title.includes('final')) {
    return {
      bg: 'bg-purple-50 text-purple-700 border-purple-200',
      dot: 'bg-[#A855F7]',
      color: '#A855F7',
      label: event.category_name || 'Examination'
    }
  }
  if (cat.includes('deadline') || title.includes('deadline') || title.includes('submission') || title.includes('due')) {
    return {
      bg: 'bg-rose-50 text-rose-700 border-rose-200',
      dot: 'bg-[#F43F5E]',
      color: '#F43F5E',
      label: event.category_name || 'Deadline'
    }
  }
  if (cat.includes('holiday') || title.includes('holiday') || title.includes('break') || title.includes('vacation')) {
    return {
      bg: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      dot: 'bg-[#10B981]',
      color: '#10B981',
      label: event.category_name || 'Holiday'
    }
  }
  if (cat.includes('event') || cat.includes('ceremony') || title.includes('orientation') || title.includes('graduation')) {
    return {
      bg: 'bg-blue-50 text-blue-700 border-blue-200',
      dot: 'bg-[#3295FF]',
      color: '#3295FF',
      label: event.category_name || 'University Event'
    }
  }
  // Academic milestones / class start
  return {
    bg: 'bg-amber-50 text-amber-800 border-amber-200',
    dot: 'bg-[#F59E0B]',
    color: event.color || '#F59E0B',
    label: event.category_name || 'Academic Milestone'
  }
}

function getStatusBadgeClass(status: string) {
  const s = (status || '').toLowerCase()
  if (s === 'upcoming') {
    return 'bg-[#EEF0FF] text-[#4F35F3] border-[#D6DBFF]'
  }
  if (s === 'ongoing') {
    return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  }
  if (s === 'completed') {
    return 'bg-slate-100 text-slate-600 border-slate-200'
  }
  if (s === 'cancelled') {
    return 'bg-rose-50 text-rose-600 border-rose-200'
  }
  return 'bg-slate-100 text-slate-600 border-slate-200'
}

function daysUntil(dateStr: string): string {
  if (!dateStr) return '-'
  const [y, m, d] = dateStr.split('-').map(Number)
  const target = new Date(y, m - 1, d).getTime()
  const now = new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime()
  const diff = Math.ceil((target - now) / 86400000)
  if (diff < 0) return `${Math.abs(diff)}d ago`
  if (diff === 0) return 'Today'
  if (diff === 1) return 'Tomorrow'
  return `In ${diff} days`
}

function daysUntilBadgeColor(dateStr: string): string {
  if (!dateStr) return 'bg-slate-100 text-slate-500'
  const [y, m, d] = dateStr.split('-').map(Number)
  const target = new Date(y, m - 1, d).getTime()
  const now = new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime()
  const diff = Math.ceil((target - now) / 86400000)
  if (diff < 0) return 'bg-slate-100 text-slate-500'
  if (diff === 0) return 'bg-[#EEF0FF] text-[#4F35F3]'
  if (diff <= 7) return 'bg-rose-50 text-rose-600'
  return 'bg-emerald-50 text-emerald-700'
}

function calculateDuration(startDate: string, endDate: string): string {
  if (!startDate || !endDate) return '1 Day'
  const start = new Date(startDate).getTime()
  const end = new Date(endDate).getTime()
  const diffDays = Math.round((end - start) / (1000 * 60 * 60 * 24)) + 1
  if (diffDays <= 1) return '1 Day'
  return `${diffDays} Days`
}

function formatDatePretty(dateStr: string): string {
  if (!dateStr) return '-'
  try {
    const [y, m, d] = dateStr.split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    })
  } catch {
    return dateStr
  }
}

// ── Navigation Functions ─────────────────────────────────
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
  selectedDateStr.value = todayStr
}

function selectDate(dateStr: string) {
  selectedDateStr.value = dateStr
}

function openEventModal(event: AcademicEvent) {
  selectedEventForModal.value = event
}

function closeEventModal() {
  selectedEventForModal.value = null
}

const handleRefresh = async () => {
  isRefreshing.value = true
  try {
    await Promise.all([
      calendarStore.fetchEvents(),
      examStore.fetchResults(),
      examStore.fetchExams(),
      fetchProfile()
    ])
  } finally {
    setTimeout(() => {
      isRefreshing.value = false
    }, 450)
  }
}

const handlePrint = () => {
  window.print()
}

onMounted(async () => {
  await Promise.all([
    fetchProfile(),
    examStore.fetchResults(),
    examStore.fetchExams(),
    calendarStore.fetchEvents()
  ])
})
</script>

<template>
  <div class="min-h-screen bg-[#F5F7FC] font-sans text-[#17243A] antialiased flex flex-col selection:bg-[#4F35F3] selection:text-white">

    <!-- Top Navigation Header -->
    <Header
      :profile="profile"
      :announcements="[]"
      @open-profile="isProfileOpen = true"
      @open-notifications="() => {}"
      @toggle-sidebar="isSidebarOpen = !isSidebarOpen"
    />

    <!-- Off-Canvas Sidebar Drawer -->
    <StudentSidebar
      :profile="profile"
      :isOpen="isSidebarOpen"
      @close="isSidebarOpen = false"
    />

    <!-- Profile Modal -->
    <ProfileModal
      v-if="isProfileOpen"
      :profile="profile"
      @close="isProfileOpen = false"
      @update-profile="(updated) => profile = updated"
    />

    <!-- ──────────────────────────────────────────────────────────── -->
    <!-- PROTECTED HERO SECTION (Structure & Background Kept 100% Intact) -->
    <!-- ──────────────────────────────────────────────────────────── -->
    <HeroSection
      :profile="profile"
      :stats="{ examsCompleted: results.length, upcomingExams: upcomingExams.length }"
      subtitle="SCHEDULE"
      title="ACADEMIC CALENDAR"
      description="View important academic events, holidays, and exam periods for the current semester."
    />

    <!-- ──────────────────────────────────────────────────────────── -->
    <!-- MAIN CONTENT BODY (Below the Hero Section) -->
    <!-- ──────────────────────────────────────────────────────────── -->
    <main class="flex-1 w-full mx-auto max-w-[1600px] px-3 sm:px-6 lg:px-8 py-5 sm:py-7 space-y-6 animate-in fade-in duration-300">

      <!-- White Canvas Main Container matching Screenshot -->
      <div class="bg-white rounded-3xl border border-[#E5EAF3] shadow-xs p-4 sm:p-6 lg:p-8 space-y-6">

        <!-- ── Section Header & Semester Status ── -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[#E5EAF3] pb-5">
          <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-[#71819B] mb-1">
              <span class="inline-flex items-center gap-1.5 text-[#4F35F3] font-bold">
                <CalendarDays :size="14" class="stroke-[2.5]" />
                Academic Schedule
              </span>
              <span>•</span>
              <span>Semester Calendar & Key Milestones</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-[#17243A] tracking-tight">Academic Calendar</h1>
            <p class="text-xs sm:text-[13px] text-[#71819B] mt-0.5">
              Stay informed about important academic dates, examinations, deadlines, and university events.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <!-- Active Semester Badge (as shown in screenshot) -->
            <span class="px-3.5 py-1.5 bg-[#EEF0FF] text-[#4F35F3] font-bold text-xs rounded-full border border-[#D6DBFF] flex items-center gap-1.5 shadow-2xs">
              <span class="w-1.5 h-1.5 rounded-full bg-[#4F35F3] animate-pulse"></span>
              {{ currentSemesterLabel }}
            </span>

            <!-- Refresh Button -->
            <button
              @click="handleRefresh"
              :disabled="isRefreshing || calendarStore.isLoadingEvents"
              class="p-2 sm:px-3 sm:py-1.5 rounded-xl border border-[#E5EAF3] bg-white text-[#71819B] hover:text-[#4F35F3] hover:border-[#D6DBFF] hover:bg-[#EEF0FF]/40 text-xs font-bold transition-all flex items-center gap-1.5 shadow-2xs disabled:opacity-50"
              title="Refresh Calendar"
            >
              <RotateCcw :size="14" :class="{ 'animate-spin': isRefreshing || calendarStore.isLoadingEvents }" />
              <span class="hidden sm:inline">Refresh</span>
            </button>

            <!-- Print Calendar Action -->
            <button
              @click="handlePrint"
              class="p-2 sm:px-3.5 sm:py-1.5 rounded-xl border border-[#E5EAF3] bg-white text-[#17243A] hover:bg-slate-50 hover:border-slate-300 text-xs font-bold transition-all flex items-center gap-1.5 shadow-2xs"
              title="Print Academic Calendar"
            >
              <Printer :size="14" class="text-[#71819B]" />
              <span class="hidden sm:inline">Print</span>
            </button>
          </div>
        </div>

        <!-- ── 4 KPI Summary Cards (Exactly matching screenshot layout & metrics) ── -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
          <div
            v-for="(stat, i) in statsCards"
            :key="i"
            class="bg-white border border-[#E5EAF3] rounded-2xl p-3.5 sm:p-5 flex items-start gap-3 sm:gap-4 hover:shadow-sm hover:border-[#D6DBFF] transition-all group"
          >
            <div :class="[stat.color, stat.border, 'w-10 h-10 sm:w-11 sm:h-11 border rounded-xl flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform']">
              <component :is="stat.icon" :size="20" class="stroke-[2.2]" />
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-[10px] sm:text-[11px] font-bold text-[#71819B] uppercase tracking-wide truncate">
                {{ stat.label }}
              </p>
              <p class="text-xl sm:text-2xl font-black text-[#17243A] leading-tight mt-0.5">
                {{ stat.value }}
              </p>
              <p class="text-[11px] text-[#71819B]/80 font-medium mt-0.5 truncate">
                {{ stat.sub }}
              </p>
            </div>
          </div>
        </div>

        <!-- ── Main 12-Column Grid (Calendar & Sidebar) ── -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

          <!-- Left 8 Columns: Main Calendar & Controls -->
          <div class="lg:col-span-8 space-y-6">

            <div class="bg-white border border-[#E5EAF3] rounded-2xl shadow-2xs p-4 sm:p-5">

              <!-- Month Toolbar Header (Prev/Next, Month Name, View Switcher) -->
              <div class="flex flex-wrap items-center justify-between gap-3 mb-4 sm:mb-5 pb-3 border-b border-[#E5EAF3]">
                <!-- Left: Navigation Arrows & Today -->
                <div class="flex items-center gap-2">
                  <div class="flex bg-slate-100/80 border border-[#E5EAF3] rounded-xl p-0.5">
                    <button
                      @click="prevMonth"
                      class="p-2 sm:px-2.5 sm:py-1.5 rounded-lg text-[#71819B] hover:bg-white hover:text-[#17243A] hover:shadow-2xs transition-all flex items-center justify-center"
                      aria-label="Previous month"
                      title="Previous month"
                    >
                      <ChevronLeft :size="16" class="stroke-[2.5]" />
                    </button>
                    <button
                      @click="nextMonth"
                      class="p-2 sm:px-2.5 sm:py-1.5 rounded-lg text-[#71819B] hover:bg-white hover:text-[#17243A] hover:shadow-2xs transition-all flex items-center justify-center"
                      aria-label="Next month"
                      title="Next month"
                    >
                      <ChevronRight :size="16" class="stroke-[2.5]" />
                    </button>
                  </div>

                  <button
                    @click="goToday"
                    class="px-3.5 py-1.5 border border-[#E5EAF3] bg-white rounded-xl text-xs font-bold text-[#17243A] hover:bg-[#EEF0FF]/50 hover:text-[#4F35F3] hover:border-[#D6DBFF] transition-all shadow-2xs"
                  >
                    Today
                  </button>
                </div>

                <!-- Center: Calendar Month & Year -->
                <div class="text-center order-first sm:order-none w-full sm:w-auto">
                  <h2 class="text-lg sm:text-xl font-black text-[#17243A] tracking-tight">
                    {{ calendarTitle }}
                  </h2>
                </div>

                <!-- Right: View Switcher (Month, Week, List) -->
                <div class="flex bg-slate-100/80 border border-[#E5EAF3] rounded-xl p-0.5">
                  <button
                    @click="viewMode = 'month'"
                    :class="[
                      'px-3 sm:px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5',
                      viewMode === 'month'
                        ? 'bg-[#4F35F3] text-white shadow-2xs'
                        : 'text-[#71819B] hover:text-[#17243A] hover:bg-white/60'
                    ]"
                  >
                    <Grid :size="13" class="stroke-[2.5]" />
                    Month
                  </button>
                  <button
                    @click="viewMode = 'week'"
                    :class="[
                      'px-3 sm:px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5',
                      viewMode === 'week'
                        ? 'bg-[#4F35F3] text-white shadow-2xs'
                        : 'text-[#71819B] hover:text-[#17243A] hover:bg-white/60'
                    ]"
                  >
                    <Clock :size="13" class="stroke-[2.5]" />
                    Week
                  </button>
                  <button
                    @click="viewMode = 'list'"
                    :class="[
                      'px-3 sm:px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5',
                      viewMode === 'list'
                        ? 'bg-[#4F35F3] text-white shadow-2xs'
                        : 'text-[#71819B] hover:text-[#17243A] hover:bg-white/60'
                    ]"
                  >
                    <List :size="13" class="stroke-[2.5]" />
                    List
                  </button>
                </div>
              </div>

              <!-- Category Filter Pills -->
              <div class="flex items-center gap-1.5 overflow-x-auto pb-2.5 mb-3 text-xs scrollbar-thin">
                <span class="text-[11px] font-bold text-[#71819B] shrink-0 mr-1 flex items-center gap-1">
                  <Filter :size="12" /> Filter:
                </span>
                <button
                  @click="selectedCategoryFilter = 'all'"
                  :class="[
                    'px-2.5 py-1 rounded-lg font-bold text-xs shrink-0 transition-all border',
                    selectedCategoryFilter === 'all'
                      ? 'bg-[#17243A] text-white border-[#17243A]'
                      : 'bg-white text-[#71819B] border-[#E5EAF3] hover:border-slate-300'
                  ]"
                >
                  All Categories
                </button>
                <button
                  @click="selectedCategoryFilter = 'exam'"
                  :class="[
                    'px-2.5 py-1 rounded-lg font-bold text-xs shrink-0 transition-all border flex items-center gap-1.5',
                    selectedCategoryFilter === 'exam'
                      ? 'bg-[#A855F7] text-white border-[#A855F7]'
                      : 'bg-white text-purple-700 border-purple-100 hover:bg-purple-50'
                  ]"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-[#A855F7]"></span>
                  Exams
                </button>
                <button
                  @click="selectedCategoryFilter = 'holiday'"
                  :class="[
                    'px-2.5 py-1 rounded-lg font-bold text-xs shrink-0 transition-all border flex items-center gap-1.5',
                    selectedCategoryFilter === 'holiday'
                      ? 'bg-[#10B981] text-white border-[#10B981]'
                      : 'bg-white text-emerald-700 border-emerald-100 hover:bg-emerald-50'
                  ]"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-[#10B981]"></span>
                  Holidays
                </button>
                <button
                  @click="selectedCategoryFilter = 'deadline'"
                  :class="[
                    'px-2.5 py-1 rounded-lg font-bold text-xs shrink-0 transition-all border flex items-center gap-1.5',
                    selectedCategoryFilter === 'deadline'
                      ? 'bg-[#F43F5E] text-white border-[#F43F5E]'
                      : 'bg-white text-rose-700 border-rose-100 hover:bg-rose-50'
                  ]"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-[#F43F5E]"></span>
                  Deadlines
                </button>
                <button
                  @click="selectedCategoryFilter = 'academic'"
                  :class="[
                    'px-2.5 py-1 rounded-lg font-bold text-xs shrink-0 transition-all border flex items-center gap-1.5',
                    selectedCategoryFilter === 'academic'
                      ? 'bg-[#F59E0B] text-white border-[#F59E0B]'
                      : 'bg-white text-amber-700 border-amber-100 hover:bg-amber-50'
                  ]"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-[#F59E0B]"></span>
                  Milestones
                </button>
              </div>

              <!-- VIEW 1: MONTH VIEW (Default Calendar Grid) -->
              <div v-if="viewMode === 'month'" class="border border-[#E5EAF3] rounded-2xl overflow-hidden bg-slate-100/40">
                <!-- Weekday Labels -->
                <div class="grid grid-cols-7 bg-white border-b border-[#E5EAF3]">
                  <div
                    v-for="day in days"
                    :key="day"
                    class="py-2.5 text-center text-[10px] sm:text-[11px] font-black text-[#71819B] uppercase tracking-wider"
                  >
                    {{ day }}
                  </div>
                </div>

                <!-- 42 Calendar Cells Grid -->
                <div class="grid grid-cols-7 gap-px bg-[#E5EAF3]">
                  <div
                    v-for="(cell, idx) in calendarCells"
                    :key="idx"
                    @click="selectDate(cell.dateStr)"
                    :class="[
                      'bg-white min-h-[64px] sm:min-h-[88px] p-1.5 sm:p-2 relative flex flex-col justify-between transition-colors cursor-pointer select-none',
                      cell.month !== 'current' ? 'opacity-40 bg-slate-50/70' : 'hover:bg-[#EEF0FF]/20',
                      cell.dateStr === todayStr ? 'ring-2 ring-inset ring-[#4F35F3] z-10' : '',
                      cell.dateStr === selectedDateStr && cell.dateStr !== todayStr ? 'ring-2 ring-inset ring-[#4F35F3]/50 bg-[#EEF0FF]/25 z-10' : ''
                    ]"
                  >
                    <!-- Day Number Header -->
                    <div class="flex items-center justify-between">
                      <span
                        :class="[
                          'text-[11px] sm:text-[12px] font-bold flex items-center justify-center w-5 h-5 sm:w-6 sm:h-6 rounded-full transition-all',
                          cell.dateStr === todayStr
                            ? 'bg-[#4F35F3] text-white shadow-xs font-black'
                            : cell.month !== 'current'
                              ? 'text-slate-400'
                              : 'text-[#17243A]'
                        ]"
                      >
                        {{ cell.day }}
                      </span>

                      <!-- Count indicator if multiple events -->
                      <span
                        v-if="eventsOnDay(cell.dateStr).length > 1"
                        class="text-[9px] font-black text-[#71819B] bg-slate-100 px-1 py-0.2 rounded-full"
                      >
                        +{{ eventsOnDay(cell.dateStr).length }}
                      </span>
                    </div>

                    <!-- Events list inside day cell -->
                    <div class="mt-1 space-y-1 overflow-hidden">
                      <div
                        v-for="ev in eventsOnDay(cell.dateStr).slice(0, 2)"
                        :key="ev.id"
                        @click.stop="openEventModal(ev)"
                        class="px-1.5 py-0.5 text-[8px] sm:text-[9.5px] font-bold rounded-md truncate cursor-pointer transition-transform hover:scale-[1.02] flex items-center gap-1 shadow-2xs"
                        :style="{
                          backgroundColor: (getCategoryTheme(ev).color || '#F59E0B') + '1A',
                          color: getCategoryTheme(ev).color || '#B45309'
                        }"
                        :title="ev.title"
                      >
                        <span
                          class="w-1.5 h-1.5 rounded-full shrink-0"
                          :style="{ backgroundColor: getCategoryTheme(ev).color || '#F59E0B' }"
                        ></span>
                        <span class="truncate">{{ ev.title }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- VIEW 2: WEEK VIEW (7 Column Expanded Slot) -->
              <div v-else-if="viewMode === 'week'" class="border border-[#E5EAF3] rounded-2xl overflow-hidden bg-white">
                <div class="grid grid-cols-1 sm:grid-cols-7 divide-y sm:divide-y-0 sm:divide-x divide-[#E5EAF3]">
                  <div
                    v-for="wDay in weekCells"
                    :key="wDay.dateStr"
                    @click="selectDate(wDay.dateStr)"
                    :class="[
                      'p-3 min-h-[220px] flex flex-col cursor-pointer transition-colors',
                      wDay.isSelected ? 'bg-[#EEF0FF]/30' : 'hover:bg-slate-50'
                    ]"
                  >
                    <!-- Header -->
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                      <div>
                        <p class="text-[10px] font-black uppercase text-[#71819B] tracking-wider">{{ wDay.dayName }}</p>
                        <p class="text-[11px] text-[#71819B]">{{ wDay.monthName }}</p>
                      </div>
                      <span
                        :class="[
                          'text-sm font-black w-7 h-7 rounded-full flex items-center justify-center',
                          wDay.isToday ? 'bg-[#4F35F3] text-white shadow-xs' : 'text-[#17243A]'
                        ]"
                      >
                        {{ wDay.day }}
                      </span>
                    </div>

                    <!-- Events list in week view -->
                    <div class="mt-2.5 flex-1 space-y-1.5">
                      <div v-if="wDay.events.length === 0" class="text-[11px] text-slate-400 py-4 text-center">
                        No events
                      </div>
                      <div
                        v-for="ev in wDay.events"
                        :key="ev.id"
                        @click.stop="openEventModal(ev)"
                        class="p-2 rounded-xl text-xs font-bold border transition-all hover:shadow-2xs cursor-pointer space-y-1"
                        :class="getCategoryTheme(ev).bg"
                      >
                        <div class="flex items-center gap-1.5">
                          <span class="w-1.5 h-1.5 rounded-full" :class="getCategoryTheme(ev).dot"></span>
                          <span class="font-bold text-[#17243A] truncate">{{ ev.title }}</span>
                        </div>
                        <div class="text-[10px] text-[#71819B] flex items-center justify-between">
                          <span>{{ ev.all_day ? 'All Day' : (ev.start_time || 'Scheduled') }}</span>
                          <span class="px-1.5 py-0.2 rounded font-semibold text-[9px]" :class="getStatusBadgeClass(ev.status)">
                            {{ ev.status }}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- VIEW 3: LIST / AGENDA VIEW -->
              <div v-else-if="viewMode === 'list'" class="border border-[#E5EAF3] rounded-2xl overflow-hidden bg-white divide-y divide-[#E5EAF3]">
                <div v-if="filteredEvents.length === 0" class="p-8 text-center text-slate-400 text-xs">
                  No academic events found for the current selection.
                </div>
                <div
                  v-for="ev in filteredEvents"
                  :key="ev.id"
                  @click="openEventModal(ev)"
                  class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/70 transition-colors cursor-pointer"
                >
                  <div class="flex items-start gap-3.5">
                    <div
                      class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border"
                      :class="getCategoryTheme(ev).bg"
                    >
                      <component :is="getCategoryTheme(ev).label === 'Examination' ? GraduationCap : Calendar" :size="18" />
                    </div>
                    <div>
                      <div class="flex items-center gap-2 flex-wrap">
                        <h4 class="text-sm font-bold text-[#17243A]">{{ ev.title }}</h4>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border" :class="getCategoryTheme(ev).bg">
                          {{ getCategoryTheme(ev).label }}
                        </span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border" :class="getStatusBadgeClass(ev.status)">
                          {{ ev.status }}
                        </span>
                      </div>
                      <p v-if="ev.description" class="text-xs text-[#71819B] mt-1 line-clamp-1">
                        {{ ev.description }}
                      </p>
                      <div class="flex items-center gap-3 text-xs text-[#71819B] mt-1.5 flex-wrap">
                        <span class="flex items-center gap-1">
                          <Calendar :size="12" /> {{ formatDatePretty(ev.start_date) }} - {{ formatDatePretty(ev.end_date) }}
                        </span>
                        <span>•</span>
                        <span>Duration: {{ calculateDuration(ev.start_date, ev.end_date) }}</span>
                      </div>
                    </div>
                  </div>

                  <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                    <span
                      class="text-xs font-bold px-2.5 py-1 rounded-xl"
                      :class="daysUntilBadgeColor(ev.start_date)"
                    >
                      {{ daysUntil(ev.start_date) }}
                    </span>
                    <button class="p-1.5 text-[#71819B] hover:text-[#4F35F3] hover:bg-[#EEF0FF] rounded-lg transition-colors">
                      <Eye :size="15" />
                    </button>
                  </div>
                </div>
              </div>

            </div>

          </div>

          <!-- Right 4 Columns: Upcoming Events & Date Context Widgets -->
          <div class="lg:col-span-4 space-y-6">

            <!-- Card 1: Selected Date Focus Panel -->
            <div class="bg-white border border-[#E5EAF3] rounded-2xl shadow-2xs p-4 sm:p-5">
              <div class="flex items-center justify-between pb-3 border-b border-[#E5EAF3]">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center font-black text-xs">
                    <CalendarCheck :size="16" />
                  </div>
                  <div>
                    <h3 class="text-sm font-bold text-[#17243A]">Selected Day</h3>
                    <p class="text-[11px] text-[#71819B]">{{ formattedSelectedDate }}</p>
                  </div>
                </div>
                <span
                  v-if="selectedDateStr === todayStr"
                  class="px-2 py-0.5 bg-[#EEF0FF] text-[#4F35F3] font-black text-[10px] rounded-full border border-[#D6DBFF]"
                >
                  TODAY
                </span>
              </div>

              <!-- Events on Selected Date -->
              <div class="mt-4 space-y-2.5">
                <div v-if="selectedDateEvents.length === 0" class="py-6 text-center">
                  <div class="w-10 h-10 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center mx-auto mb-2 text-slate-300">
                    <Calendar :size="18" />
                  </div>
                  <p class="text-xs font-bold text-[#17243A]">No Events Scheduled</p>
                  <p class="text-[11px] text-[#71819B] mt-0.5">There are no academic activities on this date.</p>
                </div>

                <div
                  v-for="ev in selectedDateEvents"
                  :key="ev.id"
                  @click="openEventModal(ev)"
                  class="p-3 rounded-xl border border-[#E5EAF3] hover:border-[#D6DBFF] hover:bg-[#EEF0FF]/20 transition-all cursor-pointer group"
                >
                  <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="w-2 h-2 rounded-full shrink-0" :class="getCategoryTheme(ev).dot"></span>
                      <h4 class="text-xs font-bold text-[#17243A] truncate group-hover:text-[#4F35F3] transition-colors">
                        {{ ev.title }}
                      </h4>
                    </div>
                    <span
                      class="text-[9px] font-bold px-1.5 py-0.5 rounded shrink-0 border"
                      :class="getStatusBadgeClass(ev.status)"
                    >
                      {{ ev.status }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between text-[11px] text-[#71819B] mt-2 pt-1 border-t border-slate-100">
                    <span>{{ ev.all_day ? 'All Day' : (ev.start_time || 'Full Period') }}</span>
                    <span class="font-semibold text-[#4F35F3] flex items-center gap-1 group-hover:underline">
                      Details <Eye :size="11" />
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Card 2: Upcoming Events Timeline (from screenshot) -->
            <div class="bg-white border border-[#E5EAF3] rounded-2xl shadow-2xs p-4 sm:p-5">
              <div class="flex items-center justify-between pb-3 border-b border-[#E5EAF3]">
                <h3 class="text-sm sm:text-[15px] font-black text-[#17243A] flex items-center gap-2">
                  <span>Upcoming Events</span>
                  <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-[#71819B]">
                    {{ calendarStore.upcomingEvents.length }}
                  </span>
                </h3>
                <span class="text-[11px] font-semibold text-[#71819B]">Semester Feed</span>
              </div>

              <!-- List of Upcoming Events -->
              <div class="mt-4 space-y-3.5">
                <div v-if="calendarStore.upcomingEvents.length === 0" class="text-xs text-[#71819B] text-center py-6">
                  No upcoming events in the calendar.
                </div>

                <div
                  v-for="ev in calendarStore.upcomingEvents"
                  :key="ev.id"
                  @click="openEventModal(ev)"
                  class="flex items-start justify-between relative pl-4 border-l-2 border-slate-100 gap-2 hover:border-[#4F35F3] transition-all cursor-pointer group pb-1"
                >
                  <!-- Timeline Dot -->
                  <span
                    class="w-2.5 h-2.5 rounded-full absolute -left-[5px] top-1 ring-2 ring-white"
                    :style="{ backgroundColor: getCategoryTheme(ev).color || '#F59E0B' }"
                  ></span>

                  <div class="min-w-0 flex-1">
                    <p class="text-xs sm:text-[13px] font-bold text-[#17243A] group-hover:text-[#4F35F3] transition-colors break-words">
                      {{ ev.title }}
                    </p>
                    <p class="text-[11px] text-[#71819B] mt-0.5">
                      {{ ev.start_date }}
                    </p>
                  </div>

                  <!-- Relative Days Badge (e.g. 98d ago / In 5 days) -->
                  <span
                    class="text-[10px] font-bold px-2 py-0.5 rounded whitespace-nowrap mt-0.5 shrink-0"
                    :class="daysUntilBadgeColor(ev.start_date)"
                  >
                    {{ daysUntil(ev.start_date) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Card 3: Academic Categories Legend -->
            <div class="bg-white border border-[#E5EAF3] rounded-2xl shadow-2xs p-4 sm:p-5">
              <h3 class="text-xs font-black uppercase text-[#71819B] tracking-wider mb-3">
                Event Category Legend
              </h3>
              <div class="space-y-2 text-xs">
                <div class="flex items-center justify-between p-2 rounded-xl bg-purple-50/60 border border-purple-100">
                  <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#A855F7]"></span>
                    <span class="font-bold text-purple-900">Examinations</span>
                  </div>
                  <span class="text-[10px] font-semibold text-purple-600">Midterms, Finals, Quizzes</span>
                </div>

                <div class="flex items-center justify-between p-2 rounded-xl bg-rose-50/60 border border-rose-100">
                  <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#F43F5E]"></span>
                    <span class="font-bold text-rose-900">Deadlines</span>
                  </div>
                  <span class="text-[10px] font-semibold text-rose-600">Submissions, Due Dates</span>
                </div>

                <div class="flex items-center justify-between p-2 rounded-xl bg-emerald-50/60 border border-emerald-100">
                  <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#10B981]"></span>
                    <span class="font-bold text-emerald-900">Holidays</span>
                  </div>
                  <span class="text-[10px] font-semibold text-emerald-600">Scheduled University Breaks</span>
                </div>

                <div class="flex items-center justify-between p-2 rounded-xl bg-amber-50/60 border border-amber-100">
                  <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#F59E0B]"></span>
                    <span class="font-bold text-amber-900">Milestones</span>
                  </div>
                  <span class="text-[10px] font-semibold text-amber-600">Class Start, Add/Drop</span>
                </div>

                <div class="flex items-center justify-between p-2 rounded-xl bg-blue-50/60 border border-blue-100">
                  <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#3295FF]"></span>
                    <span class="font-bold text-blue-900">University Events</span>
                  </div>
                  <span class="text-[10px] font-semibold text-blue-600">Ceremonies, Orientations</span>
                </div>
              </div>
            </div>

          </div>

        </div>

        <!-- ── Full Width Academic Events Schedule Table ── -->
        <div class="bg-white border border-[#E5EAF3] rounded-2xl shadow-2xs flex flex-col overflow-hidden">
          
          <!-- Table Header & Search Filter Bar -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 sm:p-5 border-b border-[#E5EAF3] gap-3 bg-white">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 border border-[#D6DBFF]">
                <CalendarDays :size="20" class="stroke-[2.2]" />
              </div>
              <div>
                <h3 class="text-sm sm:text-[15px] font-bold text-[#17243A]">Academic Events Schedule</h3>
                <p class="text-xs text-[#71819B] mt-0.5">Comprehensive semester event timeline and key academic records.</p>
              </div>
            </div>

            <!-- Search Filter Bar -->
            <div class="flex items-center gap-2 w-full sm:w-auto">
              <div class="relative w-full sm:w-72">
                <Search :size="15" class="text-slate-400 absolute left-3 top-2.5 pointer-events-none" />
                <input
                  v-model="eventSearch"
                  placeholder="Search events, dates, categories..."
                  class="w-full pl-9 pr-8 py-2 bg-slate-50 border border-[#E5EAF3] rounded-xl text-xs sm:text-[13px] focus:outline-none focus:border-[#4F35F3] focus:bg-white text-[#17243A] transition-all min-h-[38px]"
                />
                <button
                  v-if="eventSearch"
                  @click="eventSearch = ''"
                  class="absolute right-2.5 top-2.5 text-slate-400 hover:text-[#17243A]"
                >
                  <X :size="14" />
                </button>
              </div>

              <!-- Status Filter Dropdown -->
              <select
                v-model="selectedStatusFilter"
                class="bg-slate-50 border border-[#E5EAF3] text-xs font-bold text-[#71819B] rounded-xl px-2.5 py-2 focus:outline-none focus:border-[#4F35F3] focus:bg-white min-h-[38px]"
              >
                <option value="all">All Status</option>
                <option value="upcoming">Upcoming</option>
                <option value="ongoing">Ongoing</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
          </div>

          <!-- Loading State -->
          <div v-if="calendarStore.isLoadingEvents" class="flex flex-col items-center justify-center py-16 space-y-3">
            <div class="w-8 h-8 rounded-full border-3 border-[#EEF0FF] border-t-[#4F35F3] animate-spin"></div>
            <p class="text-xs font-bold text-[#71819B]">Loading academic events...</p>
          </div>

          <div v-else>
            <!-- Mobile Responsive Card View (< md) -->
            <div class="md:hidden divide-y divide-slate-100">
              <div v-if="filteredEvents.length === 0" class="p-8 text-center text-xs text-[#71819B]">
                No events match your criteria.
              </div>
              <div
                v-for="event in filteredEvents"
                :key="event.id"
                @click="openEventModal(event)"
                class="p-4 space-y-2.5 hover:bg-slate-50/60 transition-colors cursor-pointer"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="flex items-center gap-2 min-w-0">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="getCategoryTheme(event).dot"></span>
                    <h4 class="text-xs sm:text-sm font-bold text-[#17243A] break-words">{{ event.title }}</h4>
                  </div>
                  <span :class="[getStatusBadgeClass(event.status), 'text-[10px] font-bold px-2 py-0.5 rounded-full border capitalize shrink-0']">
                    {{ event.status }}
                  </span>
                </div>
                <div class="flex items-center justify-between text-xs text-[#71819B]">
                  <span class="text-[11px] font-medium text-[#71819B] flex items-center gap-1">
                    <Calendar :size="12" /> {{ formatDatePretty(event.start_date) }} - {{ formatDatePretty(event.end_date) }}
                  </span>
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border" :class="getCategoryTheme(event).bg">
                    {{ getCategoryTheme(event).label }}
                  </span>
                </div>
                <p v-if="event.description" class="text-xs text-[#71819B] pt-0.5 break-words line-clamp-2">
                  {{ event.description }}
                </p>
              </div>
            </div>

            <!-- Desktop Table View (>= md) -->
            <div class="hidden md:block overflow-x-auto min-w-0">
              <table class="w-full text-left whitespace-nowrap min-w-max">
                <thead>
                  <tr class="border-b border-[#E5EAF3] bg-slate-50/70">
                    <th class="px-5 py-3.5 text-[11px] font-black text-[#71819B] uppercase tracking-wider w-14">No.</th>
                    <th class="px-4 py-3.5 text-[11px] font-black text-[#71819B] uppercase tracking-wider">Event Title</th>
                    <th class="px-4 py-3.5 text-[11px] font-black text-[#71819B] uppercase tracking-wider">Category</th>
                    <th class="px-4 py-3.5 text-[11px] font-black text-[#71819B] uppercase tracking-wider">Start Date</th>
                    <th class="px-4 py-3.5 text-[11px] font-black text-[#71819B] uppercase tracking-wider">End Date</th>
                    <th class="px-4 py-3.5 text-[11px] font-black text-[#71819B] uppercase tracking-wider">Duration</th>
                    <th class="px-4 py-3.5 text-[11px] font-black text-[#71819B] uppercase tracking-wider">Description</th>
                    <th class="px-4 py-3.5 text-[11px] font-black text-[#71819B] uppercase tracking-wider">Status</th>
                    <th class="px-5 py-3.5 text-[11px] font-black text-[#71819B] uppercase tracking-wider text-right">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#E5EAF3]">
                  <tr v-if="filteredEvents.length === 0">
                    <td colspan="9" class="px-5 py-10 text-center text-xs text-[#71819B]">
                      No academic events found matching your search.
                    </td>
                  </tr>
                  <tr
                    v-for="(event, idx) in filteredEvents"
                    :key="event.id"
                    @click="openEventModal(event)"
                    class="hover:bg-slate-50/70 transition-colors cursor-pointer group"
                  >
                    <td class="px-5 py-3.5 text-xs font-semibold text-[#71819B]">{{ idx + 1 }}</td>
                    <td class="px-4 py-3.5">
                      <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="getCategoryTheme(event).dot"></span>
                        <span class="text-xs font-bold text-[#17243A] group-hover:text-[#4F35F3] transition-colors truncate max-w-[220px]" :title="event.title">
                          {{ event.title }}
                        </span>
                      </div>
                    </td>
                    <td class="px-4 py-3.5">
                      <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border" :class="getCategoryTheme(event).bg">
                        {{ getCategoryTheme(event).label }}
                      </span>
                    </td>
                    <td class="px-4 py-3.5 text-xs text-[#17243A] font-medium">{{ formatDatePretty(event.start_date) }}</td>
                    <td class="px-4 py-3.5 text-xs text-[#17243A] font-medium">{{ formatDatePretty(event.end_date) }}</td>
                    <td class="px-4 py-3.5 text-xs text-[#71819B] font-semibold">{{ calculateDuration(event.start_date, event.end_date) }}</td>
                    <td class="px-4 py-3.5 text-xs text-[#71819B] truncate max-w-[200px]" :title="event.description || ''">
                      {{ event.description || '-' }}
                    </td>
                    <td class="px-4 py-3.5">
                      <span :class="[getStatusBadgeClass(event.status), 'text-[10px] font-bold px-2.5 py-0.5 rounded-full border capitalize']">
                        {{ event.status }}
                      </span>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                      <button
                        @click.stop="openEventModal(event)"
                        class="p-1.5 text-[#71819B] hover:text-[#4F35F3] hover:bg-[#EEF0FF] rounded-lg transition-colors"
                        title="View Event Details"
                      >
                        <Eye :size="15" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>

    </main>

    <!-- ──────────────────────────────────────────────────────────── -->
    <!-- EVENT DETAILS MODAL (Interactive & Clean View) -->
    <!-- ──────────────────────────────────────────────────────────── -->
    <div
      v-if="selectedEventForModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-200"
      @click.self="closeEventModal"
    >
      <div class="bg-white rounded-3xl border border-[#E5EAF3] shadow-2xl max-w-lg w-full overflow-hidden animate-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="p-5 sm:p-6 border-b border-[#E5EAF3] flex items-start justify-between gap-3">
          <div class="flex items-start gap-3">
            <div
              class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 border"
              :class="getCategoryTheme(selectedEventForModal).bg"
            >
              <Calendar :size="20" class="stroke-[2.2]" />
            </div>
            <div>
              <div class="flex items-center gap-2 flex-wrap mb-1">
                <span class="text-[10px] font-black px-2 py-0.5 rounded-full border" :class="getCategoryTheme(selectedEventForModal).bg">
                  {{ getCategoryTheme(selectedEventForModal).label }}
                </span>
                <span class="text-[10px] font-black px-2 py-0.5 rounded-full border capitalize" :class="getStatusBadgeClass(selectedEventForModal.status)">
                  {{ selectedEventForModal.status }}
                </span>
              </div>
              <h3 class="text-base sm:text-lg font-black text-[#17243A]">
                {{ selectedEventForModal.title }}
              </h3>
            </div>
          </div>
          <button
            @click="closeEventModal"
            class="p-2 text-[#71819B] hover:text-[#17243A] hover:bg-slate-100 rounded-xl transition-colors"
          >
            <X :size="18" />
          </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5 sm:p-6 space-y-4">
          <!-- Dates & Schedule Info Box -->
          <div class="grid grid-cols-2 gap-3 p-4 rounded-2xl bg-slate-50 border border-[#E5EAF3]">
            <div>
              <p class="text-[10px] font-black uppercase text-[#71819B] tracking-wide">Start Date</p>
              <p class="text-xs sm:text-[13px] font-bold text-[#17243A] mt-0.5">
                {{ formatDatePretty(selectedEventForModal.start_date) }}
              </p>
              <p class="text-[11px] text-[#71819B]">{{ selectedEventForModal.start_date }}</p>
            </div>
            <div>
              <p class="text-[10px] font-black uppercase text-[#71819B] tracking-wide">End Date</p>
              <p class="text-xs sm:text-[13px] font-bold text-[#17243A] mt-0.5">
                {{ formatDatePretty(selectedEventForModal.end_date) }}
              </p>
              <p class="text-[11px] text-[#71819B]">{{ selectedEventForModal.end_date }}</p>
            </div>
            <div class="col-span-2 pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs font-semibold text-[#71819B]">
              <span>Duration: <strong class="text-[#17243A]">{{ calculateDuration(selectedEventForModal.start_date, selectedEventForModal.end_date) }}</strong></span>
              <span>Time: <strong class="text-[#17243A]">{{ selectedEventForModal.all_day ? 'All-Day' : (selectedEventForModal.start_time || 'Regular Academic Hours') }}</strong></span>
            </div>
          </div>

          <!-- Academic Scope -->
          <div class="flex items-center justify-between p-3 rounded-xl border border-[#E5EAF3] text-xs">
            <span class="font-bold text-[#71819B]">Academic Period</span>
            <span class="font-bold text-[#17243A]">
              {{ selectedEventForModal.academic_year || '2025/2026' }} • {{ selectedEventForModal.semester || 'Semester' }}
            </span>
          </div>

          <!-- Description -->
          <div v-if="selectedEventForModal.description" class="space-y-1">
            <h4 class="text-xs font-black uppercase text-[#71819B] tracking-wide">Description & Details</h4>
            <p class="text-xs sm:text-[13px] text-[#17243A] bg-slate-50/60 p-3 rounded-xl border border-[#E5EAF3] leading-relaxed">
              {{ selectedEventForModal.description }}
            </p>
          </div>

          <!-- Countdown -->
          <div class="flex items-center justify-between p-3 rounded-xl bg-[#EEF0FF]/40 border border-[#D6DBFF]">
            <span class="text-xs font-bold text-[#4F35F3]">Timeline Countdown</span>
            <span class="text-xs font-black px-2.5 py-0.5 rounded-full" :class="daysUntilBadgeColor(selectedEventForModal.start_date)">
              {{ daysUntil(selectedEventForModal.start_date) }}
            </span>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-4 sm:p-5 border-t border-[#E5EAF3] bg-slate-50 flex items-center justify-end gap-2">
          <button
            @click="closeEventModal"
            class="px-4 py-2 bg-[#4F35F3] hover:bg-[#3D25D6] text-white rounded-xl text-xs font-bold transition-all shadow-xs"
          >
            Close Details
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
@media print {
  /* Print Optimization: Hide headers/sidebars, expand calendar */
  header,
  nav,
  button,
  .no-print {
    display: none !important;
  }
}
</style>
