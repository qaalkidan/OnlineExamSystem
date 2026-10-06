<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useCalendarStore } from '../../../store/calendarStore'

const calendarStore = useCalendarStore()

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
])

// Event Search
const eventSearch = ref('')
const filteredEvents = computed(() => {
  let list = calendarStore.events
  if (eventSearch.value.trim()) {
    const q = eventSearch.value.toLowerCase()
    list = list.filter(e => e.title.toLowerCase().includes(q) || (e.category_name ?? '').toLowerCase().includes(q))
  }
  return list
})

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

// Load data on mount
import { useStudentProfile } from '../composables/useStudentProfile'
import { useStudentExamStore } from '../store/studentExamStore'

import Header from '../components/Header.vue'
import HeroSection from '../components/HeroSection.vue'
import StudentSidebar from '../components/StudentSidebar.vue'
import ProfileModal from '../components/ProfileModal.vue'

const { profile, fetchProfile } = useStudentProfile()
const examStore = useStudentExamStore()

const isSidebarOpen = ref(false)
const isProfileOpen = ref(false)

const results = computed(() => examStore.results)
const upcomingExams = computed(() => examStore.upcomingExams)

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
  <div class="min-h-screen bg-[#f8f9fc] font-sans text-slate-800 antialiased flex flex-col">

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

    <!-- Row 1: Full-Width Hero Banner (Flush under header) -->
    <HeroSection
      :profile="profile"
      :stats="{ examsCompleted: results.length, upcomingExams: upcomingExams.length }"
      subtitle="SCHEDULE"
      title="ACADEMIC CALENDAR"
      description="View important academic events, holidays, and exam periods for the current semester."
    />

    <!-- Main Content Body -->
    <main class="flex-1 w-full mx-auto max-w-[1600px] px-3 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6 animate-in fade-in duration-300">
      
      <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-4 sm:p-6 lg:p-8 space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 pb-4">
          <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800">Academic Calendar</h1>
            <p class="text-xs sm:text-[13px] text-slate-500 mt-0.5">View important academic events, holidays, and exam periods.</p>
          </div>
          <div class="flex items-center gap-3">
            <span class="px-3.5 py-1.5 bg-indigo-50 text-[#4338ca] font-bold text-xs rounded-full">2025 Second Semester</span>
          </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
          <div v-for="(stat, i) in statsData" :key="i" class="bg-white border rounded-2xl shadow-xs p-3.5 sm:p-4 flex items-start gap-3 sm:gap-4">
            <div :class="[stat.color, stat.border, 'w-9 h-9 sm:w-10 sm:h-10 border rounded-xl flex items-center justify-center shrink-0']">
              <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon"></path></svg>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-[10px] sm:text-[11px] font-bold text-slate-500 uppercase tracking-wide truncate">{{ stat.label }}</p>
              <p class="text-lg sm:text-[20px] font-black text-slate-800 leading-none mt-1">{{ stat.value }}</p>
              <p class="text-[10px] text-slate-400 font-medium mt-1 truncate">{{ stat.sub }}</p>
            </div>
          </div>
        </div>

        <!-- Main Layout Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
          
          <!-- Left Column (Calendar & Table) -->
          <div class="lg:col-span-8 space-y-6">
            
            <!-- Calendar Container -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-xs p-3.5 sm:p-5">
              <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <div class="flex items-center gap-2">
                  <div class="flex bg-slate-50 border border-slate-200 rounded-lg p-0.5">
                    <button @click="prevMonth" class="p-2 sm:px-2 sm:py-1 rounded text-slate-500 hover:bg-white hover:shadow-sm hover:text-slate-700 transition-all min-h-[36px] flex items-center justify-center" aria-label="Previous month">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    </button>
                    <button @click="nextMonth" class="p-2 sm:px-2 sm:py-1 rounded text-slate-500 hover:bg-white hover:shadow-sm hover:text-slate-700 transition-all min-h-[36px] flex items-center justify-center" aria-label="Next month">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                  </div>
                  <button @click="goToday" class="px-3 py-1.5 border border-slate-200 bg-slate-50 rounded-lg text-xs font-bold text-slate-600 hover:bg-white hover:shadow-sm transition-all min-h-[36px]">Today</button>
                </div>

                <div class="text-center order-first sm:order-none w-full sm:w-auto">
                  <h2 class="text-base sm:text-lg font-bold text-slate-800">{{ calendarTitle }}</h2>
                </div>

                <div class="flex bg-slate-50 border border-slate-200 rounded-lg p-0.5">
                  <button class="px-3 py-1 rounded-md bg-[#4338ca] shadow-sm text-xs font-bold text-white min-h-[32px]">Month</button>
                  <button class="px-3 py-1 rounded-md text-xs font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors min-h-[32px]">Week</button>
                  <button class="px-3 py-1 rounded-md text-xs font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors min-h-[32px]">List</button>
                </div>
              </div>

              <div class="grid grid-cols-7 gap-px bg-slate-200 border border-slate-200 rounded-xl overflow-hidden">
                <div v-for="day in days" :key="day" class="bg-white py-2 text-center text-[10px] sm:text-[11px] font-bold text-slate-500 uppercase tracking-wide">
                  {{ day }}
                </div>

                <!-- Dynamic calendar cells -->
                <div v-for="(cell, idx) in calendarCells" :key="idx"
                  :class="[
                    'bg-white min-h-[56px] sm:min-h-[80px] p-1 sm:p-1.5 relative overflow-hidden',
                    cell.month !== 'current' ? 'opacity-40' : '',
                    cell.dateStr === todayStr ? 'ring-2 ring-inset ring-[#4338ca]' : ''
                  ]">
                  <span :class="[
                    'text-[11px] sm:text-[12px] font-bold flex items-center justify-center w-5 h-5 sm:w-6 sm:h-6 rounded-full',
                    cell.dateStr === todayStr ? 'bg-[#4338ca] text-white' : 'text-slate-700'
                  ]">{{ cell.day }}</span>
                  <!-- Events on this day -->
                  <div v-for="ev in calendarStore.eventsOnDay(cell.dateStr).slice(0,1)" :key="ev.id"
                    class="mt-0.5 px-1 sm:px-1.5 py-0.5 text-[8px] sm:text-[9px] font-bold rounded truncate cursor-pointer"
                    :style="{ backgroundColor: ev.color + '18', color: ev.color }">
                    <span class="inline-block w-1 h-1 rounded-full mr-1 align-middle" :style="{ backgroundColor: ev.color }"></span>
                    <span class="align-middle hidden xs:inline">{{ ev.title }}</span>
                  </div>
                  <div v-if="calendarStore.eventsOnDay(cell.dateStr).length > 1" class="text-[8px] sm:text-[9px] text-slate-400 font-medium mt-0.5 pl-0.5">
                    +{{ calendarStore.eventsOnDay(cell.dateStr).length - 1 }}
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- Right Column (Sidebar Widgets) -->
          <div class="lg:col-span-4 space-y-6">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-xs p-4 sm:p-5">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm sm:text-[15px] font-bold text-slate-800">Upcoming Events</h3>
              </div>
              <div class="space-y-4">
                <div v-if="calendarStore.upcomingEvents.length === 0" class="text-xs text-slate-400 text-center py-4">No upcoming events.</div>
                <div v-for="ev in calendarStore.upcomingEvents" :key="ev.id" class="flex items-start justify-between relative pl-4 border-l border-slate-100 gap-2">
                  <!-- Timeline Dot -->
                  <span class="w-2 h-2 rounded-full absolute -left-[4px] top-1.5 ring-2 ring-white" :style="{ backgroundColor: ev.color }"></span>
                  <div class="min-w-0 flex-1">
                    <p class="text-xs sm:text-[13px] font-bold text-slate-800 break-words">{{ ev.title }}</p>
                    <p class="text-[11px] text-slate-500 mt-0.5">{{ ev.start_date }}</p>
                  </div>
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded whitespace-nowrap mt-0.5 shrink-0" :class="daysUntilColor(ev.start_date)" :style="{ backgroundColor: ev.color + '18' }">{{ daysUntil(ev.start_date) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Events Table Card -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-xs flex flex-col overflow-hidden">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 sm:p-5 border-b border-slate-100 gap-3">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#4338ca] flex items-center justify-center shrink-0 border border-indigo-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
              </div>
              <div>
                <h3 class="text-sm sm:text-[15px] font-bold text-slate-800">Academic Events</h3>
                <p class="text-xs text-slate-500 mt-0.5">Detailed list of academic events for this semester.</p>
              </div>
            </div>
            <div class="relative w-full sm:w-64">
              <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
              <input v-model="eventSearch" placeholder="Search events..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-[13px] focus:outline-none focus:border-[#4338ca] text-slate-700 min-h-[40px]" />
            </div>
          </div>

          <!-- Loading -->
          <div v-if="calendarStore.isLoadingEvents" class="flex items-center justify-center py-12">
            <svg class="w-5 h-5 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            <span class="ml-2 text-xs sm:text-[13px] text-slate-400">Loading events...</span>
          </div>

          <div v-else>
            <!-- Mobile Cards View (< md) -->
            <div class="md:hidden divide-y divide-slate-100">
              <div v-if="filteredEvents.length === 0" class="p-8 text-center text-xs text-slate-500">
                No events found.
              </div>
              <div
                v-for="(event, idx) in filteredEvents"
                :key="event.id"
                class="p-4 space-y-2 hover:bg-slate-50/50 transition-colors"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="flex items-center gap-2 min-w-0">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: event.color }"></span>
                    <h4 class="text-xs sm:text-sm font-bold text-slate-800 break-words">{{ event.title }}</h4>
                  </div>
                  <span :class="[statusBadge(event.status), 'text-[10px] font-bold px-2 py-0.5 rounded capitalize shrink-0']">
                    {{ event.status }}
                  </span>
                </div>
                <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                  <span class="text-[11px] font-semibold text-slate-500">{{ event.start_date }} - {{ event.end_date }}</span>
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded" :style="{ backgroundColor: event.color + '18', color: event.color }">
                    {{ event.category_name ?? 'Academic' }}
                  </span>
                </div>
                <p v-if="event.description" class="text-xs text-slate-500 pt-0.5 break-words">{{ event.description }}</p>
              </div>
            </div>

            <!-- Desktop Table View (>= md) -->
            <div class="hidden md:block overflow-x-auto min-w-0">
              <table class="w-full text-left whitespace-nowrap min-w-max">
                <thead>
                  <tr class="border-b border-slate-100 bg-white">
                    <th class="px-5 py-4 text-[11px] font-black text-slate-500 tracking-wider w-16">No.</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 tracking-wider">Event Title</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 tracking-wider">Category</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 tracking-wider">Start Date</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 tracking-wider">End Date</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 tracking-wider">Description</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 tracking-wider">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="filteredEvents.length === 0">
                    <td colspan="7" class="px-5 py-8 text-center text-[13px] text-slate-500">No events found.</td>
                  </tr>
                  <tr v-for="(event, idx) in filteredEvents" :key="event.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                    <td class="px-5 py-4 text-[13px] text-slate-500">{{ idx + 1 }}</td>
                    <td class="px-4 py-4">
                      <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: event.color }"></span>
                        <p class="text-[13px] font-medium text-slate-700 truncate max-w-[200px]" :title="event.title">{{ event.title }}</p>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span class="text-[10px] font-bold px-2 py-1 rounded" :style="{ backgroundColor: event.color + '18', color: event.color }">
                        {{ event.category_name ?? 'Uncategorized' }}
                      </span>
                    </td>
                    <td class="px-4 py-4 text-[13px] text-slate-600">{{ event.start_date }}</td>
                    <td class="px-4 py-4 text-[13px] text-slate-600">{{ event.end_date }}</td>
                    <td class="px-4 py-4 text-[13px] text-slate-500 truncate max-w-[200px]">{{ event.description ?? '-' }}</td>
                    <td class="px-4 py-4">
                      <span :class="[statusBadge(event.status), 'text-[10px] font-bold px-2.5 py-1 rounded capitalize']">
                        {{ event.status }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
