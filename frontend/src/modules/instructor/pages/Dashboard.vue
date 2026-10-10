<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useAuthStore } from '../../auth/store/authStore'
import { useInstructorStore } from '../store/instructorStore'
import { useSemesterLockStore } from '../store/semesterLockStore'
import StatCard from '../components/StatCard.vue'
import PerformanceChart from '../components/PerformanceChart.vue'
import RecentExamsTable from '../components/RecentExamsTable.vue'
import UpcomingExamsList from '../components/UpcomingExamsList.vue'
import QuickActions from '../components/QuickActions.vue'
import {
  FileText,
  Calendar,
  Users,
  TrendingUp,
  RefreshCw,
  CheckCircle2,
  Lock,
  Clock,
  AlertTriangle,
  ArrowRight,
  GraduationCap,
  Sparkles,
  BookOpen,
  ChevronLeft,
  ChevronRight,
  ShieldCheck,
  ExternalLink
} from 'lucide-vue-next'

const authStore = useAuthStore()
const instructorStore = useInstructorStore()
const lockStore = useSemesterLockStore()

const isRefreshing = ref(false)

// Calendar state
const currentMonthDate = ref(new Date())

const currentMonthLabel = computed(() => {
  return currentMonthDate.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const prevMonth = () => {
  const d = new Date(currentMonthDate.value)
  d.setMonth(d.getMonth() - 1)
  currentMonthDate.value = d
}

const nextMonth = () => {
  const d = new Date(currentMonthDate.value)
  d.setMonth(d.getMonth() + 1)
  currentMonthDate.value = d
}

const daysInMonth = computed(() => {
  const y = currentMonthDate.value.getFullYear()
  const m = currentMonthDate.value.getMonth()
  return new Date(y, m + 1, 0).getDate()
})

const startPaddingDays = computed(() => {
  const y = currentMonthDate.value.getFullYear()
  const m = currentMonthDate.value.getMonth()
  return new Date(y, m, 1).getDay()
})

const isToday = (day: number) => {
  const now = new Date()
  return (
    now.getDate() === day &&
    now.getMonth() === currentMonthDate.value.getMonth() &&
    now.getFullYear() === currentMonthDate.value.getFullYear()
  )
}

// Fetch data on mount
onMounted(() => {
  refreshAllData()
})

const refreshAllData = async () => {
  isRefreshing.value = true
  try {
    await Promise.all([
      instructorStore.fetchDashboardData(),
      lockStore.fetchLockStatus(true),
    ])
  } finally {
    isRefreshing.value = false
  }
}

// Responsive KPI Stat definitions
const stats = computed(() => [
  {
    title: 'Total Exams',
    value: instructorStore.stats.totalExams,
    subtitle: 'All course assessments',
    icon: FileText,
    colorClass: 'text-[#5138ed]',
    bgClass: 'bg-indigo-50',
    to: '/instructor/exams'
  },
  {
    title: 'Upcoming Exams',
    value: instructorStore.stats.upcomingExams,
    subtitle: 'Scheduled sessions',
    icon: Calendar,
    colorClass: 'text-emerald-600',
    bgClass: 'bg-emerald-50',
    to: '/instructor/exams'
  },
  {
    title: 'Enrolled Students',
    value: instructorStore.stats.totalStudents,
    subtitle: 'Active course students',
    icon: Users,
    colorClass: 'text-blue-600',
    bgClass: 'bg-blue-50',
    to: '/instructor/students'
  },
  {
    title: 'Average Score',
    value: instructorStore.stats.averageScore ? `${instructorStore.stats.averageScore}%` : '0%',
    subtitle: instructorStore.stats.attemptsCount ? 'From graded attempts' : 'No graded attempts',
    icon: TrendingUp,
    colorClass: 'text-amber-600',
    bgClass: 'bg-amber-50',
    to: '/instructor/results'
  }
])
</script>

<template>
  <div class="max-w-[1550px] mx-auto space-y-6 pb-10">

    <!-- SECTION 1: WELCOME BANNER & COURSE CONTEXT -->
    <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-start sm:items-center gap-4">
        <!-- Avatar/Icon container -->
        <div class="w-13 h-13 rounded-2xl bg-indigo-50 border border-indigo-100/80 flex items-center justify-center shrink-0 shadow-2xs text-[#5138ed]">
          <GraduationCap class="w-7 h-7" />
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight leading-tight">
              Welcome back, {{ authStore.user?.name || 'Instructor' }} 👋
            </h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-[#5138ed] border border-indigo-100 shadow-2xs">
              Instructor Portal
            </span>
          </div>
          <!-- Course & Department tags -->
          <div class="flex items-center gap-2.5 mt-1.5 text-xs text-slate-500 font-medium flex-wrap">
            <span class="flex items-center gap-1.5 text-slate-700 font-bold bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-100">
              <BookOpen class="w-3.5 h-3.5 text-[#5138ed]" />
              <span>{{ instructorStore.stats.course_code || authStore.user?.course_code || 'Course' }} — {{ instructorStore.stats.course_name || authStore.user?.course_name || 'Assigned Course' }}</span>
            </span>
            <span v-if="instructorStore.stats.department_name || authStore.user?.department?.name" class="flex items-center gap-1.5 text-slate-500">
              <span>Department of <strong>{{ instructorStore.stats.department_name || authStore.user?.department?.name }}</strong></span>
            </span>
            <span class="text-slate-300 hidden sm:inline">•</span>
            <span class="text-slate-500">
              {{ lockStore.academicYear }} ({{ lockStore.semester }})
            </span>
          </div>
        </div>
      </div>

      <!-- Header Action Controls -->
      <div class="flex items-center gap-2.5 self-start md:self-auto shrink-0">
        <button
          @click="refreshAllData"
          :disabled="isRefreshing"
          title="Refresh dashboard data"
          class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 hover:border-slate-300 transition-all shadow-xs cursor-pointer active:scale-95 disabled:opacity-60"
        >
          <RefreshCw class="w-4 h-4 text-slate-500" :class="{ 'animate-spin text-[#5138ed]': isRefreshing }" />
          <span class="hidden sm:inline">Refresh Data</span>
        </button>

        <router-link
          to="/instructor/semester-submission"
          class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-[#5138ed] hover:bg-indigo-600 transition-all shadow-sm shadow-indigo-200 cursor-pointer active:scale-95"
        >
          <span>Semester Archive</span>
          <ArrowRight class="w-3.5 h-3.5" />
        </router-link>
      </div>
    </div>

    <!-- SECTION 2: SEMESTER SUBMISSION & LOCK STATUS CARD -->
    <!-- Status: Approved & Locked -->
    <div
      v-if="lockStore.status === 'approved' || (lockStore.isLocked && lockStore.status !== 'submitted')"
      class="bg-gradient-to-br from-emerald-50 via-teal-50/50 to-emerald-50 border-2 border-emerald-200/90 rounded-2xl p-5 sm:p-6 shadow-xs relative overflow-hidden"
    >
      <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-md shadow-emerald-200/80">
          <Lock class="w-5 h-5" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <h3 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">
              Semester Approved & Locked
            </h3>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300 shadow-2xs">
              <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
              <span>Semester Approved</span>
            </div>
          </div>
          <p class="text-xs sm:text-[13px] font-bold text-emerald-950 mt-1">
            Your semester academic submission has been approved by the Department Head.
          </p>
          <p class="text-xs sm:text-[12px] text-slate-600 mt-1 leading-relaxed max-w-4xl">
            Your academic activities are now locked for this semester. You have full read-only access to view exam records, student results, and performance analytics, but new question banks, exams, or result modifications are disabled.
          </p>

          <!-- Meta Row -->
          <div class="mt-3.5 pt-3.5 border-t border-emerald-200/60 flex items-center gap-4 sm:gap-6 text-[11px] text-slate-600 font-medium flex-wrap">
            <span class="flex items-center gap-1.5 text-slate-700">
              <Calendar class="w-3.5 h-3.5 text-emerald-600" />
              <span>Period: <strong>{{ lockStore.academicYear }} ({{ lockStore.semester }})</strong></span>
            </span>
            <span v-if="lockStore.submittedAt" class="flex items-center gap-1.5 text-slate-700">
              <Clock class="w-3.5 h-3.5 text-emerald-600" />
              <span>Submitted: <strong>{{ lockStore.submittedAt }}</strong></span>
            </span>
            <span v-if="lockStore.approvedAt" class="flex items-center gap-1.5 text-slate-700">
              <ShieldCheck class="w-3.5 h-3.5 text-emerald-600" />
              <span>Approved: <strong>{{ lockStore.approvedAt }}</strong></span>
            </span>
            <span class="flex items-center gap-1.5 text-slate-700 ml-auto">
              <router-link
                to="/instructor/semester-submission"
                class="inline-flex items-center gap-1 font-bold text-emerald-800 hover:text-emerald-950 underline transition-colors"
              >
                <span>View Submission Archive</span>
                <ExternalLink class="w-3 h-3" />
              </router-link>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Status: Submitted / Under Review -->
    <div
      v-else-if="lockStore.status === 'submitted'"
      class="bg-gradient-to-br from-amber-50 via-orange-50/40 to-amber-50 border-2 border-amber-200/90 rounded-2xl p-5 sm:p-6 shadow-xs relative overflow-hidden"
    >
      <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-2xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-md shadow-amber-200/80">
          <Clock class="w-5 h-5" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <h3 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">
              Semester Submitted — Under Review
            </h3>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-amber-100 text-amber-800 border border-amber-300 shadow-2xs">
              <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
              <span>Under Department Review</span>
            </div>
          </div>
          <p class="text-xs sm:text-[13px] font-bold text-amber-950 mt-1">
            Your semester academic submission is currently awaiting Department Head approval.
          </p>
          <p class="text-xs sm:text-[12px] text-slate-600 mt-1 leading-relaxed">
            Academic modifications are temporarily locked in read-only mode while the Department Head reviews your exams and final course records.
          </p>
          <div class="mt-3.5 pt-3.5 border-t border-amber-200/60 flex items-center gap-4 sm:gap-6 text-[11px] text-slate-600 font-medium flex-wrap">
            <span>Period: <strong>{{ lockStore.academicYear }} ({{ lockStore.semester }})</strong></span>
            <span v-if="lockStore.submittedAt">Submitted: <strong>{{ lockStore.submittedAt }}</strong></span>
            <router-link to="/instructor/semester-submission" class="font-bold text-amber-800 hover:text-amber-950 underline ml-auto">
              View Review Status →
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- Status: Correction Required / Rejected -->
    <div
      v-else-if="lockStore.status === 'correction_required' || lockStore.status === 'rejected'"
      class="bg-gradient-to-br from-rose-50 via-red-50/40 to-rose-50 border-2 border-rose-200/90 rounded-2xl p-5 sm:p-6 shadow-xs relative overflow-hidden"
    >
      <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-2xl bg-rose-600 text-white flex items-center justify-center shrink-0 shadow-md shadow-rose-200/80">
          <AlertTriangle class="w-5 h-5" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <h3 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">
              Submission Requires Correction
            </h3>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-rose-100 text-rose-800 border border-rose-300 shadow-2xs">
              <span class="w-2 h-2 rounded-full bg-rose-600"></span>
              <span>Action Required</span>
            </div>
          </div>
          <p class="text-xs sm:text-[13px] font-bold text-rose-950 mt-1">
            The Department Head has requested revisions on your semester records.
          </p>
          <p class="text-xs sm:text-[12px] text-slate-600 mt-1 leading-relaxed">
            Please check the revision remarks on your submission review page, make the necessary corrections, and re-submit your final records.
          </p>
          <div class="mt-3.5 pt-3.5 border-t border-rose-200/60 flex items-center justify-between text-[11px] text-slate-600 font-medium">
            <span>Period: <strong>{{ lockStore.academicYear }} ({{ lockStore.semester }})</strong></span>
            <router-link to="/instructor/semester-submission" class="font-bold text-rose-700 hover:text-rose-900 underline">
              Open Revision Checklist →
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- SECTION 3: KPI SUMMARY STAT CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Loading Skeletons -->
      <template v-if="instructorStore.isLoading">
        <div v-for="i in 4" :key="i" class="bg-white rounded-2xl p-5 border border-slate-100 shadow-xs animate-pulse flex items-center gap-4">
          <div class="w-12 h-12 bg-slate-100 rounded-xl shrink-0"></div>
          <div class="flex-1 space-y-2">
            <div class="h-3 bg-slate-100 rounded w-3/4"></div>
            <div class="h-6 bg-slate-100 rounded w-1/2"></div>
            <div class="h-3 bg-slate-100 rounded w-full"></div>
          </div>
        </div>
      </template>

      <!-- Real Stat Cards -->
      <StatCard
        v-else
        v-for="(stat, index) in stats"
        :key="index"
        v-bind="stat"
      />
    </div>

    <!-- SECTION 4: MAIN 2-COLUMN DASHBOARD GRID -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

      <!-- LEFT COLUMN (8 Cols): Recent Exams & Performance Analytics -->
      <div class="xl:col-span-8 space-y-6">

        <!-- Recent Exams Table -->
        <RecentExamsTable
          :exams="instructorStore.recentExams"
          :is-loading="instructorStore.isLoading"
        />

        <!-- Performance Overview & Analytics Card -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-6 shadow-xs">
          <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center">
                <TrendingUp class="w-4.5 h-4.5" />
              </div>
              <div>
                <h2 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">Performance Overview</h2>
                <p class="text-[12px] text-slate-400">Course score analytics & graded assessment performance</p>
              </div>
            </div>

            <router-link
              to="/instructor/results"
              class="text-xs font-bold text-[#5138ed] hover:text-indigo-700 transition-colors"
            >
              View Detailed Results →
            </router-link>
          </div>

          <div class="flex flex-col lg:flex-row gap-6 pt-2">
            <!-- Chart Section -->
            <div class="flex-1 lg:border-r border-slate-100 lg:pr-6 overflow-hidden">
              <PerformanceChart :performance-data="instructorStore.stats.performanceByExam" />
            </div>

            <!-- Key Metrics Grid -->
            <div class="w-full lg:w-52 grid grid-cols-3 lg:grid-cols-1 gap-3 sm:gap-4 justify-center items-center">
              <!-- Average Score -->
              <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 text-center lg:text-left">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Average Score</div>
                <div class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">
                  {{ instructorStore.stats.averageScore ? `${instructorStore.stats.averageScore}%` : '0.0%' }}
                </div>
                <div class="text-[10px] text-slate-400 mt-0.5">From graded attempts</div>
              </div>

              <!-- Highest Score -->
              <div class="p-3.5 rounded-xl bg-emerald-50/60 border border-emerald-100 text-center lg:text-left">
                <div class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider">Highest Score</div>
                <div class="text-xl sm:text-2xl font-black text-emerald-800 mt-0.5">
                  {{ instructorStore.stats.highestScore !== null && instructorStore.stats.highestScore !== undefined ? `${instructorStore.stats.highestScore}%` : '—' }}
                </div>
                <div class="text-[10px] text-emerald-600 mt-0.5">Top student result</div>
              </div>

              <!-- Lowest Score -->
              <div class="p-3.5 rounded-xl bg-rose-50/60 border border-rose-100 text-center lg:text-left">
                <div class="text-[11px] font-bold text-rose-700 uppercase tracking-wider">Lowest Score</div>
                <div class="text-xl sm:text-2xl font-black text-rose-800 mt-0.5">
                  {{ instructorStore.stats.lowestScore !== null && instructorStore.stats.lowestScore !== undefined ? `${instructorStore.stats.lowestScore}%` : '—' }}
                </div>
                <div class="text-[10px] text-rose-600 mt-0.5">Minimum score recorded</div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- RIGHT COLUMN (4 Cols): Upcoming Exams, Quick Actions, Calendar -->
      <div class="xl:col-span-4 space-y-6">

        <!-- Upcoming Scheduled Exams List -->
        <UpcomingExamsList
          :exams="instructorStore.upcomingExams"
          :is-loading="instructorStore.isLoading"
        />

        <!-- Quick Actions Panel -->
        <QuickActions />

        <!-- Interactive Course Calendar Widget -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-6 shadow-xs">
          <div class="flex items-center justify-between mb-4 text-slate-800">
            <button
              type="button"
              @click="prevMonth"
              class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors cursor-pointer"
              aria-label="Previous month"
            >
              <ChevronLeft class="w-4 h-4" />
            </button>
            <span class="text-xs sm:text-sm font-bold text-slate-900">{{ currentMonthLabel }}</span>
            <button
              type="button"
              @click="nextMonth"
              class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors cursor-pointer"
              aria-label="Next month"
            >
              <ChevronRight class="w-4 h-4" />
            </button>
          </div>

          <!-- Day Headers -->
          <div class="grid grid-cols-7 gap-1 text-center mb-1">
            <div
              v-for="day in ['Su','Mo','Tu','We','Th','Fr','Sa']"
              :key="day"
              class="text-[10px] font-bold text-slate-400 py-1"
            >
              {{ day }}
            </div>
          </div>

          <!-- Calendar Days Grid -->
          <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-slate-700">
            <!-- Blank padding -->
            <div v-for="i in startPaddingDays" :key="`pad-${i}`" class="py-1.5"></div>
            <!-- Days in month -->
            <div
              v-for="d in daysInMonth"
              :key="d"
              class="py-1.5 rounded-lg flex items-center justify-center transition-colors cursor-pointer"
              :class="isToday(d) ? 'bg-[#5138ed] text-white shadow-xs font-bold' : 'hover:bg-slate-50 text-slate-700'"
            >
              {{ d }}
            </div>
          </div>
        </div>

      </div>

    </div>

  </div>
</template>
