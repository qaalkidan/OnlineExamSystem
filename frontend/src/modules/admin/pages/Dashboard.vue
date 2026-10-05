<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '../../../core/api/apiClient'

const selectedPeriod = ref('Last 30 Days')
const isLoading = ref(true)

// Reactive states for the dashboard
const stats = ref([
  { label: 'Total Users',  value: '0', change: '+0%', sub: 'All registered users',  color: 'indigo', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { label: 'Instructors',  value: '0', change: '+0%', sub: 'Active instructors',     color: 'green',  icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
  { label: 'Students',     value: '0', change: '+0%', sub: 'Active students',         color: 'blue',   icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
  { label: 'Courses',      value: '0', change: '+0%', sub: 'Offered courses',         color: 'orange', icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10' },
  { label: 'Exams',        value: '0', change: '+0%', sub: 'Total exams created',     color: 'rose',   icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
])

const colorMap: Record<string, { bg: string, icon: string, text: string, badge: string }> = {
  indigo: { bg: 'bg-indigo-50', icon: 'text-indigo-500', text: 'text-indigo-600', badge: 'bg-indigo-100 text-indigo-600' },
  green:  { bg: 'bg-green-50',  icon: 'text-green-500',  text: 'text-green-600',  badge: 'bg-green-100 text-green-600'  },
  blue:   { bg: 'bg-blue-50',   icon: 'text-blue-500',   text: 'text-blue-600',   badge: 'bg-blue-100 text-blue-600'   },
  orange: { bg: 'bg-orange-50', icon: 'text-orange-500', text: 'text-orange-600', badge: 'bg-orange-100 text-orange-600'},
  rose:   { bg: 'bg-rose-50',   icon: 'text-rose-500',   text: 'text-rose-600',   badge: 'bg-rose-100 text-rose-600'   },
}

const systemMetrics = ref([
  { label: 'Exams Conducted', value: '0',    change: '+0%', color: 'text-indigo-500' },
  { label: 'Total Attempts',  value: '0',    change: '+0%', color: 'text-green-500'  },
  { label: 'Pass Rate',       value: '0%',   change: '+0%', color: 'text-orange-500' },
  { label: 'Average Score',   value: '0%',   change: '+0%', color: 'text-blue-500'   },
])

const systemStatus = ref([
  { name: 'System Server', icon: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2', color: 'text-green-500', bg: 'bg-green-50' },
  { name: 'Database',      icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4',                      color: 'text-green-500', bg: 'bg-green-50' },
  { name: 'File Storage',  icon: 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12',                                           color: 'text-green-500', bg: 'bg-green-50' },
  { name: 'Email Service', icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',                          color: 'text-green-500', bg: 'bg-green-50' },
])

const recentExams = ref<any[]>([])

const fetchDashboardStats = async () => {
  try {
    const response = await apiClient.get('/admin/dashboard-stats')
    const data = response.data
    
    // Update top cards
    stats.value[0].value = data.stats.totalUsers.toLocaleString()
    stats.value[1].value = data.stats.instructors.toLocaleString()
    stats.value[2].value = data.stats.students.toLocaleString()
    stats.value[3].value = data.stats.courses.toLocaleString()
    stats.value[4].value = data.stats.exams.toLocaleString()
    
    // Update system metrics
    systemMetrics.value[0].value = data.overview.examsConducted.toLocaleString()
    systemMetrics.value[1].value = data.overview.totalAttempts.toLocaleString()
    systemMetrics.value[2].value = data.overview.passRate + '%'
    systemMetrics.value[3].value = data.overview.averageScore + '%'

    // Update recent exams
    recentExams.value = data.recentExams || []

    // Update donut distribution
    const totalUsers = data.stats.totalUsers || 1 // prevent div zero
    donutDistribution.value[0].count = data.stats.students
    donutDistribution.value[0].pct = ((data.stats.students / totalUsers) * 100).toFixed(1) + '%'

    donutDistribution.value[1].count = data.stats.instructors
    donutDistribution.value[1].pct = ((data.stats.instructors / totalUsers) * 100).toFixed(1) + '%'

    donutDistribution.value[2].count = data.stats.deptHeads
    donutDistribution.value[2].pct = ((data.stats.deptHeads / totalUsers) * 100).toFixed(1) + '%'

    // Update Overview Chart
    if (data.chart) {
      chartLabels.value = data.chart.labels
      const rawData = data.chart.data
      const maxVal = Math.max(...rawData, 10) // min max of 10
      // Map 7 points to x from 0 to 600, y from 180 to 20
      chartPoints.value = rawData.map((val: number, idx: number) => {
        const x = idx * (600 / 6)
        const y = 180 - ((val / maxVal) * 160)
        return [x, y]
      })
    }
    
  } catch (error) {
    console.error('Failed to fetch dashboard stats:', error)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchDashboardStats()
})

const quickActions = [
  { label: 'Create New Exam',   icon: 'M12 4v16m8-8H4',                                                       path: '/admin/exams'        },
  { label: 'Add New User',      icon: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z', path: '/admin/users'        },
  { label: 'Manage Instructors',icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', path: '/admin/instructors'  },
  { label: 'View Reports',      icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', path: '/admin/reports' },
]

const donutDistribution = ref([
  { label: 'Students',         count: 0, pct: '0%', color: '#5138ed' },
  { label: 'Instructors',      count: 0, pct: '0%', color: '#22c55e' },
  { label: 'Department Head',  count: 0, pct: '0%', color: '#f97316' },
])

// SVG line chart data (simplified wave points)
const chartLabels = ref(['Apr 27', 'May 02', 'May 07', 'May 12', 'May 17', 'May 22', 'May 27'])
const chartPoints = ref([
  [0,300],[30,260],[60,280],[90,220],[120,240],[150,200],[180,210],[210,180],[240,200],[270,160],[300,180],[330,150],[360,170],[390,140],[420,160],[450,130],[480,150],[510,170],[540,145],[570,160],[600,140]
])

const svgPath = computed(() => {
  if (chartPoints.value.length === 0) return ''
  return 'M ' + chartPoints.value.map(p => `${p[0]},${p[1]}`).join(' L ')
})
const svgFill = computed(() => {
  if (chartPoints.value.length === 0) return ''
  return `M 0,200 L 0,300 ` + chartPoints.value.map(p => `L ${p[0]},${p[1]}`).join(' ') + ` L 600,200 Z`
})

const donutSegments = computed(() => {
  const C = 2 * Math.PI * 44;
  let currentOffset = 0;
  return donutDistribution.value.map(d => {
    const pctVal = parseFloat(d.pct) / 100 || 0;
    const length = pctVal * C;
    const dasharray = `${length} ${C - length}`;
    const dashoffset = -currentOffset;
    currentOffset += length;
    return {
      color: d.color,
      dasharray,
      dashoffset
    };
  });
})
</script>

<template>
  <div class="space-y-6">

    <!-- Page Actions -->
    <div class="flex items-center justify-end">
      <div class="flex items-center gap-2 text-[13px] font-medium text-slate-600 border border-slate-200 rounded-xl px-4 py-2.5 bg-white shadow-sm cursor-pointer hover:border-indigo-300 transition-colors">
        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        May 27, 2025
        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4">
      <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-5 flex items-start gap-3 sm:gap-4 hover:shadow-md transition-shadow">
        <div :class="[colorMap[stat.color].bg, 'w-10 h-10 sm:w-11 sm:h-11 rounded-xl flex items-center justify-center shrink-0']">
          <svg class="w-5 h-5" :class="colorMap[stat.color].icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon"></path>
          </svg>
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider truncate">{{ stat.label }}</p>
          <div class="flex items-center gap-1.5 sm:gap-2 mt-0.5 flex-wrap">
            <span class="text-[18px] sm:text-[20px] font-bold text-slate-800">{{ stat.value }}</span>
            <span :class="[colorMap[stat.color].badge, 'text-[10px] font-bold px-1.5 py-0.5 rounded-md']">{{ stat.change }}</span>
          </div>
          <p class="text-[11px] text-slate-400 font-medium mt-1 truncate">{{ stat.sub }}</p>
        </div>
      </div>
    </div>

    <!-- Row 2: System Overview + System Status -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
      
      <!-- System Overview Chart -->
      <div class="col-span-1 lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6 min-w-0">
        <div class="flex items-center justify-between mb-5 flex-wrap gap-2">
          <h3 class="text-[15px] font-bold text-slate-800">System Overview</h3>
          <div class="flex items-center gap-2 text-[12px] font-semibold text-slate-600 border border-slate-200 rounded-lg px-3 py-1.5 cursor-pointer hover:border-indigo-300 transition-colors">
            {{ selectedPeriod }}
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
        </div>
        <!-- Metrics Row -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-5">
          <div v-for="m in systemMetrics" :key="m.label" class="flex items-center gap-2">
            <span :class="[m.color, 'w-2 h-2 rounded-full flex-shrink-0']" style="display:inline-block"></span>
            <div class="min-w-0">
              <p class="text-[11px] font-medium text-slate-500 leading-none truncate">{{ m.label }}</p>
              <p class="text-[13px] font-bold text-slate-800 truncate">{{ m.value }} <span class="text-[10px] font-semibold text-green-500">{{ m.change }}</span></p>
            </div>
          </div>
        </div>
        <!-- SVG Chart -->
        <div class="relative overflow-x-auto min-w-0">
          <svg viewBox="0 0 600 200" class="w-full h-44 min-w-[320px]" preserveAspectRatio="none">
            <!-- Fill -->
            <defs>
              <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#5138ed" stop-opacity="0.12"/>
                <stop offset="100%" stop-color="#5138ed" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <path :d="svgFill" fill="url(#chartGrad)" />
            <path :d="svgPath" fill="none" stroke="#5138ed" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <!-- X axis labels -->
          <div class="flex justify-between mt-1 px-1 min-w-[320px]">
            <span v-for="label in chartLabels" :key="label" class="text-[10px] text-slate-400 font-medium">{{ label }}</span>
          </div>
        </div>
      </div>

      <!-- System Status -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6 min-w-0">
        <h3 class="text-[15px] font-bold text-slate-800 mb-5">System Status</h3>
        <div class="space-y-4">
          <div v-for="item in systemStatus" :key="item.name" class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 min-w-0">
              <div :class="[item.bg, 'w-9 h-9 rounded-xl flex items-center justify-center shrink-0']">
                <svg class="w-4.5 h-4.5" :class="item.color" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon"></path>
                </svg>
              </div>
              <span class="text-[13px] font-semibold text-slate-700 truncate">{{ item.name }}</span>
            </div>
            <span class="text-[12px] font-bold text-green-500 shrink-0">Operational</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Row 3: User Distribution + Recent Exams + Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6">

      <!-- User Distribution Donut -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6 min-w-0">
        <h3 class="text-[15px] font-bold text-slate-800 mb-5">User Distribution</h3>
        <div class="flex flex-col items-center">
          <!-- Donut SVG -->
          <div class="relative mb-5">
            <svg viewBox="0 0 120 120" class="w-36 h-36">
              <!-- Background ring -->
              <circle cx="60" cy="60" r="44" fill="none" stroke="#f1f5f9" stroke-width="18"/>
              <!-- Segments -->
              <circle v-for="seg in donutSegments" :key="seg.color" 
                cx="60" cy="60" r="44" fill="none" :stroke="seg.color" stroke-width="18"
                :stroke-dasharray="seg.dasharray" :stroke-dashoffset="seg.dashoffset" 
                stroke-linecap="butt" transform="rotate(-90 60 60)"/>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
              <span class="text-[18px] font-bold text-slate-800">{{ stats[0].value }}</span>
              <span class="text-[10px] font-medium text-slate-400">Total Users</span>
            </div>
          </div>
          <!-- Legend -->
          <div class="w-full space-y-2">
            <div v-for="d in donutDistribution" :key="d.label" class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: d.color }"></span>
                <span class="text-[12px] font-medium text-slate-600">{{ d.label }}</span>
              </div>
              <span class="text-[12px] font-semibold text-slate-700">{{ d.count.toLocaleString() }} ({{ d.pct }})</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Exams -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6 min-w-0">
        <div class="flex items-center justify-between mb-5">
          <h3 class="text-[15px] font-bold text-slate-800">Recent Exams</h3>
          <router-link to="/admin/exams" class="text-[12px] font-bold text-[#5138ed] hover:underline">View All</router-link>
        </div>
        <!-- Header -->
        <div class="grid grid-cols-[1fr_auto_auto] gap-2 pb-2 border-b border-slate-100 mb-2">
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Exam Title</span>
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider text-right">Date</span>
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider text-right">Status</span>
        </div>
        <div class="space-y-3">
          <div v-for="exam in recentExams" :key="exam.title" class="grid grid-cols-[1fr_auto_auto] gap-2 items-center">
            <div class="min-w-0">
              <p class="text-[12px] font-bold text-slate-800 leading-snug truncate">{{ exam.title }}</p>
              <p class="text-[10px] text-slate-400 font-medium truncate">{{ exam.course }}</p>
            </div>
            <span class="text-[11px] text-slate-500 font-medium text-right whitespace-nowrap">{{ exam.date }}</span>
            <span class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-md whitespace-nowrap">{{ exam.status }}</span>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6 min-w-0">
        <h3 class="text-[15px] font-bold text-slate-800 mb-5">Quick Actions</h3>
        <div class="space-y-3">
          <router-link
            v-for="action in quickActions"
            :key="action.label"
            :to="action.path"
            class="flex items-center justify-between p-3.5 rounded-xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/40 transition-all group cursor-pointer"
          >
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center group-hover:bg-indigo-100 transition-colors">
                <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="action.icon"></path>
                </svg>
              </div>
              <span class="text-[13px] font-semibold text-slate-700 group-hover:text-[#5138ed] transition-colors">{{ action.label }}</span>
            </div>
            <svg class="w-4 h-4 text-slate-300 group-hover:text-[#5138ed] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </router-link>
        </div>
      </div>
    </div>

  </div>
</template>
