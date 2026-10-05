<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useAuthStore } from '../../auth/store/authStore'
import { useInstructorStore } from '../store/instructorStore'
import { useSemesterLockStore } from '../store/semesterLockStore'
import StatCard from '../components/StatCard.vue'
import PerformanceChart from '../components/PerformanceChart.vue'
import RecentExamsTable from '../components/RecentExamsTable.vue'
import UpcomingExamsList from '../components/UpcomingExamsList.vue'
import QuickActions from '../components/QuickActions.vue'

const authStore = useAuthStore()
const instructorStore = useInstructorStore()
const lockStore = useSemesterLockStore()

// Fetch data on mount
onMounted(() => {
  instructorStore.fetchDashboardData()
  lockStore.fetchLockStatus()
})

// Derive stat card definitions reactively from store data
const stats = computed(() => [
  {
    title: 'Total Exams',
    value: instructorStore.stats.totalExams,
    subtitle: 'All Created Exams',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
    colorClass: 'text-[#5138ed]',
    bgClass: 'bg-indigo-50'
  },
  {
    title: 'Upcoming Exams',
    value: instructorStore.stats.upcomingExams,
    subtitle: 'Scheduled Exams',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>',
    colorClass: 'text-emerald-500',
    bgClass: 'bg-emerald-50'
  },
  {
    title: 'Total Students',
    value: instructorStore.stats.totalStudents,
    subtitle: 'Enrolled in course',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>',
    colorClass: 'text-blue-500',
    bgClass: 'bg-blue-50'
  },
  {
    title: 'Average Score',
    value: `${instructorStore.stats.averageScore}%`,
    subtitle: 'Overall Performance',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>',
    colorClass: 'text-orange-500',
    bgClass: 'bg-orange-50'
  }
])
</script>

<template>
  <div class="max-w-7xl mx-auto flex flex-col xl:flex-row gap-5 lg:gap-6">

    <!-- Main Left Column -->
    <div class="flex-1 min-w-0 space-y-5 lg:space-y-6">

      <!-- Semester Submitted / Locked Banner -->
      <div
        v-if="lockStore.isLocked"
        class="bg-gradient-to-br from-emerald-50 via-teal-50/60 to-emerald-50 border-2 border-emerald-300/80 rounded-2xl p-4 sm:p-5 shadow-xs relative overflow-hidden"
      >
        <div class="flex items-start gap-3 sm:gap-4">
          <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-md shadow-emerald-200">
            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2 flex-wrap">
              <h3 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">
                Semester Submitted
              </h3>
              <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 sm:px-3 sm:py-1 rounded-full text-xs font-black bg-emerald-100/90 text-emerald-800 border border-emerald-300 shadow-xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                🟢 {{ lockStore.status === 'approved' ? 'Semester Approved' : 'Semester Submitted' }}
              </div>
            </div>
            <p class="text-xs sm:text-[13px] font-bold text-emerald-950 mt-1">
              Your semester academic submission has been successfully submitted.
            </p>
            <p class="text-xs sm:text-[12px] text-slate-600 mt-1 leading-relaxed">
              Your academic activities are now locked for this semester. You can view your academic records and results, but you cannot make changes.
            </p>
            <div class="mt-3 pt-3 border-t border-emerald-200/60 flex items-center gap-3 sm:gap-5 text-[11px] text-slate-500 font-medium flex-wrap">
              <span class="flex items-center gap-1.5 text-slate-700">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Period: <strong>{{ lockStore.academicYear }} ({{ lockStore.semester }})</strong>
              </span>
              <span v-if="lockStore.submittedAt" class="flex items-center gap-1.5 text-slate-700">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Submitted: <strong>{{ lockStore.submittedAt }}</strong>
              </span>
              <span class="flex items-center gap-1.5 text-slate-700">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Mode: <strong class="text-emerald-700 font-extrabold uppercase">Read-Only Access</strong>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Greeting -->
      <div class="mb-1">
        <h1 class="text-xl sm:text-2xl font-extrabold text-slate-800 flex items-center gap-2">
          Welcome back, {{ authStore.user?.name || 'Dr. Abebe Kebede' }} <span class="text-xl sm:text-2xl">👋</span>
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1 flex items-center gap-1.5 truncate">
          <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
          <span v-if="instructorStore.stats.course_code" class="truncate">
            Currently managing <strong>{{ instructorStore.stats.course_code }} — {{ instructorStore.stats.course_name }}</strong>
          </span>
          <span v-else>Loading course info...</span>
        </p>
      </div>

      <!-- Dev Banner: Mock Data Active -->
      <div
        v-if="instructorStore.usingMockData"
        class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 text-xs sm:text-sm font-medium flex items-center gap-2"
      >
        <svg class="w-4 h-4 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>
          <strong>Dev Mode:</strong> Backend is offline — showing mock data.
          Start the server with <code class="bg-amber-100 px-1 rounded font-mono text-xs">php artisan serve</code> for live data.
        </span>
      </div>

      <!-- Error Banner: Real API Error -->
      <div v-if="instructorStore.error" class="bg-rose-50 border border-rose-200 text-rose-700 rounded-xl px-3 sm:px-4 py-2.5 sm:py-3 text-xs sm:text-sm font-medium flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        {{ instructorStore.error }}
      </div>

      <!-- 4 Stat Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <!-- Skeleton loaders -->
        <template v-if="instructorStore.isLoading">
          <div v-for="i in 4" :key="i" class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100 shadow-xs animate-pulse flex items-center gap-4">
            <div class="w-12 h-12 bg-slate-100 rounded-xl shrink-0"></div>
            <div class="flex-1 space-y-2">
              <div class="h-3 bg-slate-100 rounded w-3/4"></div>
              <div class="h-6 bg-slate-100 rounded w-1/2"></div>
              <div class="h-3 bg-slate-100 rounded w-full"></div>
            </div>
          </div>
        </template>

        <!-- Real stat cards -->
        <StatCard v-else v-for="(stat, index) in stats" :key="index" v-bind="stat" />
      </div>

      <!-- Recent Exams -->
      <RecentExamsTable
        :exams="instructorStore.recentExams"
        :is-loading="instructorStore.isLoading"
      />

      <!-- Performance Overview -->
      <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-6 shadow-xs">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
          <h2 class="text-base sm:text-lg font-bold text-slate-800">Performance Overview</h2>
          <select class="text-xs sm:text-sm border border-slate-200 text-slate-600 rounded-lg px-3 py-1.5 focus:outline-none focus:border-[#5138ed] min-h-[36px]">
            <option>This Semester</option>
            <option>Last Semester</option>
            <option>This Year</option>
          </select>
        </div>
        <div class="flex flex-col lg:flex-row gap-6">
          <!-- Chart Section -->
          <div class="flex-1 lg:border-r border-slate-100 lg:pr-6 overflow-hidden">
            <PerformanceChart />
          </div>
          <!-- Key Metrics -->
          <div class="w-full lg:w-48 lg:pl-2 grid grid-cols-3 lg:grid-cols-1 gap-3 sm:gap-4 lg:gap-6 pt-4 lg:pt-0 border-t lg:border-t-0 border-slate-100 justify-center">
            <div class="text-center lg:text-left">
              <div class="flex items-center justify-center lg:justify-start gap-1.5 text-blue-500 mb-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
              </div>
              <div class="text-lg sm:text-xl font-extrabold text-slate-800">{{ instructorStore.stats.averageScore }}%</div>
              <div class="text-[11px] font-semibold text-slate-400">Average Score</div>
            </div>
            <div class="text-center lg:text-left">
              <div class="flex items-center justify-center lg:justify-start gap-1.5 text-emerald-500 mb-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
              </div>
              <div class="text-lg sm:text-xl font-extrabold text-slate-800">95.0%</div>
              <div class="text-[11px] font-semibold text-slate-400">Highest Score</div>
            </div>
            <div class="text-center lg:text-left">
              <div class="flex items-center justify-center lg:justify-start gap-1.5 text-rose-500 mb-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
              </div>
              <div class="text-lg sm:text-xl font-extrabold text-slate-800">78.0%</div>
              <div class="text-[11px] font-semibold text-slate-400">Lowest Score</div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Right Sidebar Column -->
    <div class="w-full xl:w-[320px] space-y-5 lg:space-y-6 shrink-0">
      <UpcomingExamsList
        :exams="instructorStore.upcomingExams"
        :is-loading="instructorStore.isLoading"
      />
      <QuickActions />

      <!-- Mock Calendar Widget -->
      <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-6 shadow-xs">
        <div class="flex items-center justify-between mb-4 text-slate-800">
          <button type="button" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg min-h-[36px] min-w-[36px] flex items-center justify-center" aria-label="Previous month">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
          </button>
          <span class="text-xs sm:text-sm font-bold">{{ new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' }) }}</span>
          <button type="button" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg min-h-[36px] min-w-[36px] flex items-center justify-center" aria-label="Next month">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          </button>
        </div>
        <div class="grid grid-cols-7 gap-1 text-center mb-2">
          <div class="text-[11px] font-semibold text-slate-400 py-1" v-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day">{{ day }}</div>
        </div>
        <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-slate-600">
          <div class="py-1.5 text-slate-300" v-for="i in new Date(new Date().getFullYear(), new Date().getMonth(), 1).getDay()" :key="`pad-${i}`"></div>
          <div
            v-for="i in new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).getDate()"
            :key="i"
            class="py-1.5 cursor-pointer rounded-lg transition-colors flex items-center justify-center"
            :class="i === new Date().getDate() ? 'bg-[#5138ed] text-white shadow-xs shadow-indigo-200' : 'hover:bg-slate-50'"
          >{{ i }}</div>
        </div>
      </div>
    </div>

  </div>
</template>
