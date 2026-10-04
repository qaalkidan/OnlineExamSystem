<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import { Doughnut } from 'vue-chartjs'
import apiClient from '../../../core/api/apiClient'

ChartJS.register(ArcElement, Tooltip, Legend)

const search = ref('')
const statusFilter = ref('all')
const courseFilter = ref('all')
const departmentFilter = ref('all')
const typeFilter = ref('all')
const yearFilter = ref('all')
const semesterFilter = ref('all')
const currentPage = ref(1)
const perPage = 10
const showDeleteModal = ref(false)
const showDetailsPage = ref(false)
const showAllQuestions = ref(false)
const selectedExam = ref<any>(null)
const isLoading = ref(true)

const openDetailsPage = async (exam: any) => {
  try {
    const res = await apiClient.get(`/admin/exams/${exam.id}`)
    selectedExam.value = res.data.data
    showDetailsPage.value = true
  } catch (error) {
    console.error('Failed to load exam details', error)
  }
}

const allExams = ref<any[]>([])

const allCourses = computed(() => [...new Set(allExams.value.map(e => e.course))])
const allDepartments = computed(() => [...new Set(allExams.value.map(e => e.department))])
const allYears = computed(() => [...new Set(allExams.value.map(e => e.year))])
const allSemesters = computed(() => [...new Set(allExams.value.map(e => e.semester))])
const examTypes = computed(() => [...new Set(allExams.value.map(e => e.type))])

const filtered = computed(() =>
  allExams.value.filter(e => {
    const matchSearch = e.title.toLowerCase().includes(search.value.toLowerCase()) ||
                        (e.course || '').toLowerCase().includes(search.value.toLowerCase()) ||
                        (e.instructor || '').toLowerCase().includes(search.value.toLowerCase()) ||
                        (e.courseCode || '').toLowerCase().includes(search.value.toLowerCase())
    const matchStatus = statusFilter.value === 'all' || e.status === statusFilter.value
    const matchCourse = courseFilter.value === 'all' || e.course === courseFilter.value
    const matchDept   = departmentFilter.value === 'all' || e.department === departmentFilter.value
    const matchYear   = yearFilter.value === 'all' || e.year === yearFilter.value
    const matchSem    = semesterFilter.value === 'all' || e.semester === semesterFilter.value
    const matchType   = typeFilter.value === 'all' || e.type === typeFilter.value
    return matchSearch && matchStatus && matchCourse && matchDept && matchYear && matchSem && matchType
  })
)

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const paginated  = computed(() => filtered.value.slice((currentPage.value - 1) * perPage, currentPage.value * perPage))

const statsData = ref({
  total:     0,
  published: 0,
  draft:     0,
  scheduled: 0,
  completed: 0,
})

const upcomingExams = computed(() =>
  allExams.value.filter(e => e.status === 'scheduled' || e.status === 'published').slice(0, 4)
)

const statusConfig: Record<string, { badge: string; label: string }> = {
  published: { badge: 'bg-emerald-50 text-emerald-700',   label: 'Published'  },
  completed: { badge: 'bg-indigo-50 text-indigo-700',     label: 'Completed'  },
  scheduled: { badge: 'bg-amber-50 text-amber-700',       label: 'Scheduled'  },
  draft:     { badge: 'bg-slate-100 text-slate-500',      label: 'Draft'      },
  active:    { badge: 'bg-emerald-50 text-emerald-700',   label: 'Active'     },
}

const typeConfig: Record<string, string> = {
  Quiz:       'bg-sky-50 text-sky-700',
  Midterm:    'bg-amber-50 text-amber-700',
  'Mid Exam': 'bg-amber-50 text-amber-700',
  Final:      'bg-rose-50 text-rose-600',
  'Final Exam': 'bg-rose-50 text-rose-600',
  Assignment: 'bg-violet-50 text-violet-700',
}

const confirmDelete = (exam: any) => { selectedExam.value = exam; showDeleteModal.value = true }
const deleteExam    = async () => { 
  if (selectedExam.value) {
    try {
      await apiClient.delete(`/admin/exams/${selectedExam.value.id}`)
      allExams.value = allExams.value.filter(e => e.id !== selectedExam.value.id);
      
      // Update stats based on deleted exam status
      const status = selectedExam.value.status;
      statsData.value.total--;
      if (status === 'published') statsData.value.published--;
      else if (status === 'draft') statsData.value.draft--;
      else if (status === 'scheduled') statsData.value.scheduled--;
      else if (status === 'completed') statsData.value.completed--;
    } catch (error) {
      console.error('Failed to delete exam', error)
    }
  }
  showDeleteModal.value = false 
}

// Chart
const chartData = computed(() => ({
  labels: ['Published', 'Draft', 'Scheduled', 'Completed'],
  datasets: [{
    backgroundColor: ['#4338ca', '#10B981', '#F59E0B', '#F97316'],
    data: [statsData.value.published, statsData.value.draft, statsData.value.scheduled, statsData.value.completed],
    borderWidth: 0,
    hoverOffset: 4
  }]
}))

const groupedQuestions = computed(() => {
  if (!selectedExam.value || !selectedExam.value.questions) return []
  
  const groups: Record<string, any[]> = {}
  selectedExam.value.questions.forEach((q: any) => {
    const inst = q.instruction || 'General Questions'
    if (!groups[inst]) groups[inst] = []
    groups[inst].push(q)
  })
  
  return Object.keys(groups).map(inst => ({
    instruction: inst,
    questions: groups[inst]
  }))
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '72%',
  plugins: {
    legend: { display: false },
    tooltip: { enabled: true }
  }
}

onMounted(async () => {
  try {
    const response = await apiClient.get('/admin/exams')
    allExams.value = response.data.data.exams
    statsData.value = response.data.data.stats
  } catch (error) {
    console.error('Failed to fetch admin exams', error)
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="space-y-6 min-w-0 w-full">
    <!-- List View -->
    <div v-if="!showDetailsPage && !showAllQuestions" class="space-y-6 min-w-0 w-full">

    <!-- Page Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-end gap-4">
      <div class="flex flex-wrap items-center gap-3">
        <button class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-[#4338ca] font-bold rounded-xl text-[13px] hover:bg-slate-50 transition-colors shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg> Export Exams
        </button>
      </div>
    </div>

    <!-- Stats Cards (5 cards) -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-5">
      <div v-for="(card, i) in [
        { label:'Total Exams',      val: statsData.total,     trend:'↑ 10.1%', tc:'text-emerald-500', sub:'All exams created',  bg:'bg-indigo-50',  ic:'text-[#4338ca]',    icon:'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
        { label:'Published Exams',  val: statsData.published, trend:'↑ 12.7%', tc:'text-emerald-500', sub:'Published exams',   bg:'bg-emerald-50', ic:'text-emerald-500',  icon:'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
        { label:'Draft Exams',      val: statsData.draft,     trend:'↓ 6.7%',  tc:'text-rose-500',    sub:'Draft exams',       bg:'bg-rose-50',    ic:'text-rose-500',     icon:'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' },
        { label:'Scheduled Exams',  val: statsData.scheduled, trend:'↑ 15.2%', tc:'text-emerald-500', sub:'Upcoming exams',   bg:'bg-amber-50',   ic:'text-amber-500',    icon:'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
        { label:'Completed Exams',  val: statsData.completed, trend:'↑ 9.5%',  tc:'text-emerald-500', sub:'Finished exams',   bg:'bg-sky-50',     ic:'text-sky-500',      icon:'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
      ]" :key="i" class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4">
        <div :class="[card.bg, 'w-12 h-12 rounded-xl flex items-center justify-center shrink-0']">
          <svg class="w-6 h-6" :class="card.ic" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="card.icon"></path></svg>
        </div>
        <div>
          <p class="text-[11px] font-bold text-slate-500 tracking-wide">{{ card.label }}</p>
          <h3 class="text-[22px] font-black text-slate-800 leading-none mt-0.5">{{ card.val }}</h3>
          <div class="flex items-center gap-1 mt-1 text-[11px]">
            <span :class="card.tc" class="font-bold">{{ card.trend }}</span>
            <span class="text-slate-400">{{ card.sub }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Layout: Full-width Table -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm flex flex-col min-w-0 overflow-hidden">

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-2 px-5 py-3.5 border-b border-slate-100">
          <div class="relative flex-1 min-w-[160px]">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input v-model="search" placeholder="Search Exams..." class="w-full pl-9 pr-4 py-2 text-[12px] border border-slate-200 rounded-lg focus:outline-none focus:border-[#4338ca] bg-white">
          </div>
          <select v-model="courseFilter" class="text-[12px] border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#4338ca] text-slate-600 bg-white">
            <option value="all">All Courses</option>
            <option v-for="c in allCourses" :key="c" :value="c">{{ c }}</option>
          </select>
          <select v-model="departmentFilter" class="text-[12px] border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#4338ca] text-slate-600 bg-white">
            <option value="all">All Departments</option>
            <option v-for="d in allDepartments" :key="d" :value="d">{{ d }}</option>
          </select>
          <select v-model="yearFilter" class="text-[12px] border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#4338ca] text-slate-600 bg-white">
            <option value="all">All Years</option>
            <option v-for="y in allYears" :key="y" :value="y">{{ y }}</option>
          </select>
          <select v-model="semesterFilter" class="text-[12px] border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#4338ca] text-slate-600 bg-white">
            <option value="all">All Semesters</option>
            <option v-for="s in allSemesters" :key="s" :value="s">{{ s }}</option>
          </select>
          <select v-model="typeFilter" class="text-[12px] border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#4338ca] text-slate-600 bg-white">
            <option value="all">All Exam Types</option>
            <option v-for="t in examTypes" :key="t" :value="t">{{ t }}</option>
          </select>
          <select v-model="statusFilter" class="text-[12px] border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#4338ca] text-slate-600 bg-white">
            <option value="all">All Status</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
            <option value="scheduled">Scheduled</option>
            <option value="completed">Completed</option>
          </select>
          <button class="flex items-center gap-1.5 px-3 py-2 border border-slate-200 text-[#4338ca] font-bold rounded-lg text-[12px] hover:bg-slate-50 ml-auto">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg> Filter
          </button>
        </div>

        <!-- Data Table -->
        <div class="overflow-x-auto min-w-0">
          <table class="w-full text-left whitespace-nowrap min-w-max">
            <thead>
              <tr class="border-b border-slate-100">
                <th class="px-5 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Department</th>
                <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Course</th>
                <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Instructor</th>
                <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Exam Type</th>
                <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Year Level</th>
                <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Section</th>
                <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Duration</th>
                <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Status</th>
                <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Exam Date</th>
                <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="exam in paginated" :key="exam.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors group">
                <td class="px-5 py-4">
                  <p class="text-[13px] font-bold text-slate-800 leading-snug">{{ exam.department }}</p>
                  <p class="text-[10px] font-mono text-slate-400 mt-0.5">{{ exam.examCode }}</p>
                </td>
                <td class="px-4 py-4">
                  <p class="text-[12px] font-bold text-slate-700">{{ exam.course }}</p>
                  <p class="text-[10px] font-mono text-slate-400 mt-0.5">{{ exam.courseCode }}</p>
                </td>
                <td class="px-4 py-4">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-indigo-100 text-[#4338ca] flex items-center justify-center text-[10px] font-bold shrink-0">{{ exam.instructor.split(' ').map((w: string) => w[0]).join('').slice(0,2) }}</div>
                    <div>
                      <p class="text-[12px] font-bold text-slate-700 leading-none">{{ exam.instructor }}</p>
                      <p class="text-[10px] text-slate-400 mt-0.5">{{ exam.instructorEmail }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-4 text-center">
                  <span :class="[typeConfig[exam.type] || 'bg-slate-50 text-slate-600', 'text-[10px] font-bold px-2.5 py-1 rounded-lg']">{{ exam.type }}</span>
                </td>
                <td class="px-4 py-4 text-center">
                  <span class="text-[12px] font-semibold text-slate-700">{{ exam.year || '—' }}</span>
                </td>
                <td class="px-4 py-4 text-center">
                  <span class="text-[12px] font-semibold text-slate-700">{{ exam.section || '—' }}</span>
                </td>
                <td class="px-4 py-4 text-center">
                  <span class="text-[12px] font-semibold text-slate-600">{{ exam.duration }}</span>
                </td>
                <td class="px-4 py-4 text-center">
                  <span :class="[statusConfig[exam.status]?.badge || 'bg-slate-100 text-slate-500', 'text-[10px] font-bold px-2.5 py-1 rounded-lg capitalize']">{{ statusConfig[exam.status]?.label || exam.status }}</span>
                </td>
                <td class="px-4 py-4">
                  <p class="text-[12px] text-slate-700 font-semibold">{{ exam.examDate || '—' }}</p>
                  <p v-if="exam.examTime" class="text-[10px] text-slate-400 mt-0.5">{{ exam.examTime }}</p>
                </td>
                <td class="px-4 py-4">
                  <div class="flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button @click="openDetailsPage(exam)" class="w-7 h-7 rounded bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg></button>
                  </div>
                </td>
              </tr>
              <tr v-if="paginated.length === 0">
                <td colspan="10" class="py-16 text-center text-[13px] text-slate-400">No exams found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-5 py-4 border-t border-slate-100 bg-slate-50/30">
          <p class="text-[12px] text-slate-500">Showing <span class="font-bold text-slate-700">{{ (currentPage-1)*perPage+1 }}</span> to <span class="font-bold text-slate-700">{{ Math.min(currentPage*perPage, filtered.length) }}</span> of <span class="font-bold text-slate-700">{{ filtered.length }}</span> exams</p>
          <div class="flex gap-1">
            <button @click="currentPage = Math.max(1, currentPage-1)" :disabled="currentPage===1" class="w-8 h-8 rounded border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 bg-white disabled:opacity-40">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            <button v-for="p in Math.min(totalPages, 3)" :key="p" @click="currentPage = p" :class="[currentPage===p ? 'bg-[#4338ca] text-white border-[#4338ca]' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50', 'w-8 h-8 rounded border flex items-center justify-center text-[12px] font-bold transition-colors']">{{ p }}</button>
            <span v-if="totalPages > 3" class="w-8 h-8 flex items-center justify-center text-slate-400 text-[13px]">...</span>
            <button v-if="totalPages > 3" @click="currentPage = totalPages" class="w-8 h-8 rounded border border-slate-200 bg-white text-slate-600 text-[12px] font-bold hover:bg-slate-50">{{ totalPages }}</button>
            <button @click="currentPage = Math.min(totalPages, currentPage+1)" :disabled="currentPage===totalPages" class="w-8 h-8 rounded border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 bg-white disabled:opacity-40">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
          </div>
        </div>
    </div>

    <!-- Bottom Cards: Exam Overview, Top Exam Types, Upcoming Exams, Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">

      <!-- Exam Overview Chart -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-bold text-slate-800 mb-5">Exam Overview</h3>
        <div class="relative h-44 w-full">
          <Doughnut :data="chartData" :options="chartOptions" />
          <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
            <h4 class="text-[22px] font-black text-slate-800 leading-none">342</h4>
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">Total Exams</p>
          </div>
        </div>
        <div class="space-y-2.5 mt-5">
          <div v-for="(item, i) in [
            { label:'Published', count: 228, pct:'66.7%', color:'bg-[#4338ca]' },
            { label:'Draft',     count: 56,  pct:'16.4%', color:'bg-emerald-500' },
            { label:'Scheduled', count: 42,  pct:'12.3%', color:'bg-amber-400' },
            { label:'Completed', count: 198, pct:'57.9%', color:'bg-orange-500' },
          ]" :key="i" class="flex items-center justify-between text-[12px]">
            <div class="flex items-center gap-2"><div :class="[item.color, 'w-2 h-2 rounded-full']"></div><span class="font-bold text-slate-700">{{ item.label }}</span></div>
            <span class="text-slate-500"><span class="font-bold text-slate-700">{{ item.count }}</span> ({{ item.pct }})</span>
          </div>
        </div>
      </div>

      <!-- Top Exam Types -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
        <h3 class="text-[14px] font-bold text-slate-800 mb-4">Top Exam Types</h3>
        <div class="space-y-3">
          <div v-for="(et, i) in [
            { name: 'Quizzes',     count: 128 },
            { name: 'Midterms',    count: 96 },
            { name: 'Finals',      count: 78 },
            { name: 'Assignments', count: 40 },
          ]" :key="i" class="flex items-center justify-between text-[12px]">
            <span class="text-slate-600 font-medium">{{ et.name }}</span>
            <span class="font-bold text-slate-800 bg-slate-50 px-2 py-0.5 rounded">{{ et.count }}</span>
          </div>
        </div>
      </div>

      <!-- Upcoming Exams -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-[14px] font-bold text-slate-800">Upcoming Exams</h3>
          <button class="text-[11px] font-bold text-[#4338ca] hover:text-indigo-800 transition-colors">View All</button>
        </div>
        <div class="space-y-3">
          <div v-for="(exam, i) in upcomingExams" :key="i" class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
              <svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[12px] font-bold text-slate-800 truncate">{{ exam.title }}</p>
              <p class="text-[10px] text-slate-400 mt-0.5">{{ exam.examDate }} {{ exam.examTime }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
        <h3 class="text-[14px] font-bold text-slate-800 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 gap-3">
          <button class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50 transition-colors text-[#4338ca]">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            <span class="text-[10px] font-bold text-center">Create New<br>Exam</span>
          </button>
          <button class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition-colors text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            <span class="text-[10px] font-bold text-center">Import<br>Exams</span>
          </button>
          <button class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition-colors text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
            <span class="text-[10px] font-bold text-center">Export<br>Exams</span>
          </button>
          <button class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition-colors text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
            <span class="text-[10px] font-bold text-center">Manage Exam<br>Types</span>
          </button>
        </div>
      </div>

    </div>
    </div> <!-- Close List View -->

    <!-- Exam Details View -->
    <div v-if="showDetailsPage && !showAllQuestions" class="space-y-6 pb-12 min-w-0 w-full">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-[24px] font-bold text-slate-800 leading-tight">{{ selectedExam?.title || 'Exam Details' }}</h1>
              <span :class="[statusConfig[selectedExam?.status]?.badge || 'bg-slate-100 text-slate-500', 'text-[10px] font-bold px-2.5 py-1 rounded-lg capitalize']">{{ statusConfig[selectedExam?.status]?.label || selectedExam?.status }}</span>
              <span class="text-[12px] font-mono font-bold text-[#4338ca] bg-indigo-50 px-2 py-0.5 rounded">{{ selectedExam?.examCode }}</span>
            </div>
            <div class="flex items-center gap-2 text-[11px] font-bold text-slate-400 mt-1">
              <span>Exams</span>
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              <span class="text-slate-600">Exam List</span>
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              <span class="text-slate-600">Exam Details</span>
            </div>
          </div>
        </div>
        <button @click="showDetailsPage = false" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-600 font-bold rounded-xl text-[13px] hover:bg-slate-50 transition-colors shadow-sm whitespace-nowrap">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg> Back to Exam List
        </button>
      </div>

      <!-- Content -->
      <div class="space-y-6">
        
        <!-- Questions Section at the TOP -->
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
          <div class="flex items-center justify-between mb-6">
            <div>
              <h2 class="text-[18px] font-bold text-slate-800">Exam Questions</h2>
              <p class="text-[13px] text-slate-500 mt-0.5">Total Questions: {{ selectedExam?.questions?.length || 0 }} | Total Marks: {{ selectedExam?.totalMarks || selectedExam?.questions?.reduce((sum: number, q: any) => sum + (Number(q.marks) || 0), 0) || 0 }}</p>
            </div>
            <div class="flex items-center gap-3">
               <!-- Quick Actions at top right -->
            </div>
          </div>

          <!-- Loading state if questions aren't loaded -->
          <div v-if="!selectedExam?.questions" class="text-center py-10 text-slate-500 text-sm">
             Loading questions...
          </div>
          
          <div v-else-if="groupedQuestions.length === 0" class="text-center py-10 text-slate-500 text-sm">
             No questions found in this exam.
          </div>

          <!-- Render Questions by Instruction -->
          <div v-else class="space-y-8">
             <div v-for="(group, gIdx) in groupedQuestions" :key="gIdx" class="space-y-4">
                <!-- Instruction block -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                   <p class="text-[14px] font-bold text-slate-800">Instruction:</p>
                   <div class="text-[13px] text-slate-600 mt-1 prose prose-sm max-w-none" v-html="group.instruction"></div>
                </div>

                <!-- Questions in this instruction -->
                <div class="space-y-4 pl-4 border-l-2 border-[#4338ca]/20 ml-2">
                   <div v-for="(q, qIdx) in group.questions" :key="q.id" class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm">
                      <div class="flex items-start justify-between gap-4 mb-3">
                         <div class="flex gap-3">
                            <span class="w-6 h-6 rounded bg-indigo-50 text-[#4338ca] font-bold text-[12px] flex items-center justify-center shrink-0">{{ qIdx + 1 }}</span>
                            <div class="text-[14px] font-medium text-slate-800 mt-0.5 prose prose-sm max-w-none" v-html="q.text"></div>
                         </div>
                         <div class="flex items-center gap-2">
                           <span class="text-[10px] font-bold text-[#4338ca] bg-indigo-50 px-2 py-1 rounded shrink-0 uppercase tracking-wider">{{ q.type?.replace('_', ' ') || 'Unknown' }}</span>
                           <span class="text-[11px] font-bold text-slate-500 bg-slate-50 px-2 py-1 rounded shrink-0">{{ q.marks }} Marks</span>
                         </div>
                      </div>

                      <!-- Options based on question type -->
                      <div v-if="(q.type === 'multiple_choice' || q.type === 'true_false' || q.type === 'Multiple Choice (MCQ)' || q.type === 'True / False') && q.options" class="grid grid-cols-1 sm:grid-cols-2 gap-2 ml-9">
                         <div v-for="(opt, oIdx) in q.options" :key="oIdx" class="bg-slate-50 border-slate-100 text-slate-600 px-3 py-2 rounded-lg border text-[13px] flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full border flex items-center justify-center text-[10px] shrink-0 border-slate-300 bg-white">{{ opt.label || String.fromCharCode(65 + Number(oIdx)) }}</span>
                            <span v-html="opt.text || opt"></span>
                         </div>
                      </div>
                      
                      <!-- Matching Options -->
                      <div v-else-if="q.type === 'matching' && q.options" class="ml-9 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Left Side -->
                        <div class="space-y-2">
                          <p class="text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Premises</p>
                          <div v-for="(opt, idx) in q.options.filter((o: any) => o.type==='left')" :key="idx" class="flex items-center gap-2 bg-slate-50 px-3 py-2 rounded-lg border border-slate-100 text-sm text-slate-700">
                            <span class="w-5 h-5 rounded-full bg-white border border-slate-300 flex items-center justify-center text-[10px] font-bold shrink-0">{{ Number(idx) + 1 }}</span>
                            <span v-html="opt.text"></span>
                          </div>
                        </div>
                        <!-- Right Side -->
                        <div class="space-y-2">
                          <p class="text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Responses</p>
                          <div v-for="(opt, idx) in q.options.filter((o: any) => o.type==='right')" :key="idx" class="flex items-center gap-2 bg-slate-50 px-3 py-2 rounded-lg border border-slate-100 text-sm text-slate-700">
                            <span class="w-5 h-5 rounded-full bg-white border border-slate-300 flex items-center justify-center text-[10px] font-bold shrink-0 uppercase">{{ String.fromCharCode(65 + (Number(idx) % 26)) }}</span>
                            <span v-html="opt.text"></span>
                          </div>
                        </div>
                      </div>
                   </div>
                </div>
             </div>
          </div>
        </div>

        <!-- Bottom Layout: 3 Horizontal Cards -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-stretch">
          
          <!-- Exam Info Card -->
          <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm space-y-4">
            <h2 class="text-[14px] font-bold text-slate-800 mb-2 flex items-center gap-2"><svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Exam Information</h2>
            
            <div class="flex items-center justify-between text-[12px] border-b border-slate-50 pb-2">
              <div class="flex items-center gap-2 text-slate-500 font-medium">Course</div>
              <div class="font-bold text-slate-800 text-right">{{ selectedExam?.course }}<br><span class="text-slate-400 font-normal text-[10px]">({{ selectedExam?.courseCode }})</span></div>
            </div>
            
            <div class="flex items-center justify-between text-[12px] border-b border-slate-50 pb-2">
              <div class="flex items-center gap-2 text-slate-500 font-medium">Department</div>
              <span class="font-bold text-slate-800">{{ selectedExam?.instructor?.department?.name || selectedExam?.department || 'Not set' }}</span>
            </div>

            <div class="flex items-center justify-between text-[12px] border-b border-slate-50 pb-2">
              <div class="flex items-center gap-2 text-slate-500 font-medium">Year Level</div>
              <span class="font-bold text-slate-800">{{ selectedExam?.instructor?.year_level || selectedExam?.year || 'Not set' }}</span>
            </div>

            <div class="flex items-center justify-between text-[12px] border-b border-slate-50 pb-2">
              <div class="flex items-center gap-2 text-slate-500 font-medium">Instructor</div>
              <span class="font-bold text-slate-800">{{ selectedExam?.instructor?.name || selectedExam?.instructor }}</span>
            </div>

            <div class="flex items-center justify-between text-[12px] border-b border-slate-50 pb-2">
              <div class="flex items-center gap-2 text-slate-500 font-medium">Exam Type</div>
              <span class="font-bold text-slate-800">{{ selectedExam?.type }}</span>
            </div>

            <div class="flex items-center justify-between text-[12px] border-b border-slate-50 pb-2">
              <div class="flex items-center gap-2 text-slate-500 font-medium">Date</div>
              <span class="font-bold text-slate-800">{{ selectedExam?.examDate || 'Not set' }}</span>
            </div>

            <div class="flex items-center justify-between text-[12px] border-b border-slate-50 pb-2">
              <div class="flex items-center gap-2 text-slate-500 font-medium">Duration</div>
              <span class="font-bold text-slate-800">{{ selectedExam?.duration }}</span>
            </div>

            <div class="flex items-center justify-between text-[12px]">
              <div class="flex items-center gap-2 text-slate-500 font-medium">Time</div>
              <span class="font-bold text-slate-800">{{ selectedExam?.examTime || 'Not set' }}</span>
            </div>
          </div>

          <!-- Exam Behavior -->
          <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <h2 class="text-[14px] font-bold text-slate-800 mb-5 flex items-center gap-2"><svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg> Exam Behavior</h2>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <p class="text-[12px] font-bold text-slate-800">Shuffle Questions</p>
                <div :class="[selectedExam?.shuffle_questions ? 'bg-[#4338ca]' : 'bg-slate-200', 'w-8 h-5 rounded-full relative shrink-0 cursor-not-allowed transition-colors']"><div :class="[selectedExam?.shuffle_questions ? 'right-1' : 'left-1', 'w-3 h-3 bg-white rounded-full absolute top-1 shadow-sm transition-all']"></div></div>
              </div>
              <div class="flex items-center justify-between">
                <p class="text-[12px] font-bold text-slate-800">Show Review Screen</p>
                <div :class="[selectedExam?.show_review_screen ? 'bg-[#4338ca]' : 'bg-slate-200', 'w-8 h-5 rounded-full relative shrink-0 cursor-not-allowed transition-colors']"><div :class="[selectedExam?.show_review_screen ? 'right-1' : 'left-1', 'w-3 h-3 bg-white rounded-full absolute top-1 shadow-sm transition-all']"></div></div>
              </div>
              <div class="flex items-center justify-between">
                <p class="text-[12px] font-bold text-slate-800">Shuffle Answer Options</p>
                <div :class="[selectedExam?.shuffle_options ? 'bg-[#4338ca]' : 'bg-slate-200', 'w-8 h-5 rounded-full relative shrink-0 cursor-not-allowed transition-colors']"><div :class="[selectedExam?.shuffle_options ? 'right-1' : 'left-1', 'w-3 h-3 bg-white rounded-full absolute top-1 shadow-sm transition-all']"></div></div>
              </div>
              <div class="flex items-center justify-between">
                <p class="text-[12px] font-bold text-slate-800">Allow Backtracking</p>
                <div :class="[selectedExam?.allow_backtracking ? 'bg-[#4338ca]' : 'bg-slate-200', 'w-8 h-5 rounded-full relative shrink-0 cursor-not-allowed transition-colors']"><div :class="[selectedExam?.allow_backtracking ? 'right-1' : 'left-1', 'w-3 h-3 bg-white rounded-full absolute top-1 shadow-sm transition-all']"></div></div>
              </div>
              <div class="flex items-center justify-between">
                <p class="text-[12px] font-bold text-slate-800">Show One Question at a Time</p>
                <div :class="[selectedExam?.show_one_question ? 'bg-[#4338ca]' : 'bg-slate-200', 'w-8 h-5 rounded-full relative shrink-0 cursor-not-allowed transition-colors']"><div :class="[selectedExam?.show_one_question ? 'right-1' : 'left-1', 'w-3 h-3 bg-white rounded-full absolute top-1 shadow-sm transition-all']"></div></div>
              </div>
            </div>
          </div>

          <!-- Security Settings -->
          <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <h2 class="text-[14px] font-bold text-slate-800 mb-5 flex items-center gap-2"><svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg> Security Settings</h2>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <p class="text-[12px] font-bold text-slate-800">Enable Fullscreen Mode</p>
                <div :class="[selectedExam?.fullscreen_mode ? 'bg-[#4338ca]' : 'bg-slate-200', 'w-8 h-5 rounded-full relative shrink-0 cursor-not-allowed transition-colors']"><div :class="[selectedExam?.fullscreen_mode ? 'right-1' : 'left-1', 'w-3 h-3 bg-white rounded-full absolute top-1 shadow-sm transition-all']"></div></div>
              </div>
              <div class="flex items-center justify-between">
                <p class="text-[12px] font-bold text-slate-800">Browser Tab Monitoring</p>
                <div :class="[selectedExam?.tab_monitoring ? 'bg-[#4338ca]' : 'bg-slate-200', 'w-8 h-5 rounded-full relative shrink-0 cursor-not-allowed transition-colors']"><div :class="[selectedExam?.tab_monitoring ? 'right-1' : 'left-1', 'w-3 h-3 bg-white rounded-full absolute top-1 shadow-sm transition-all']"></div></div>
              </div>
              <div class="flex items-center justify-between">
                <p class="text-[12px] font-bold text-slate-800">Disable Right Click</p>
                <div :class="[selectedExam?.disable_right_click ? 'bg-[#4338ca]' : 'bg-slate-200', 'w-8 h-5 rounded-full relative shrink-0 cursor-not-allowed transition-colors']"><div :class="[selectedExam?.disable_right_click ? 'right-1' : 'left-1', 'w-3 h-3 bg-white rounded-full absolute top-1 shadow-sm transition-all']"></div></div>
              </div>
              <div class="flex items-center justify-between">
                <p class="text-[12px] font-bold text-slate-800">Allow Calculator</p>
                <div :class="[selectedExam?.allow_calculator ? 'bg-[#4338ca]' : 'bg-slate-200', 'w-8 h-5 rounded-full relative shrink-0 cursor-not-allowed transition-colors']"><div :class="[selectedExam?.allow_calculator ? 'right-1' : 'left-1', 'w-3 h-3 bg-white rounded-full absolute top-1 shadow-sm transition-all']"></div></div>
              </div>
              <div class="flex items-center justify-between">
                <p class="text-[12px] font-bold text-slate-800">Disable Copy & Paste</p>
                <div :class="[selectedExam?.disable_copy_paste ? 'bg-[#4338ca]' : 'bg-slate-200', 'w-8 h-5 rounded-full relative shrink-0 cursor-not-allowed transition-colors']"><div :class="[selectedExam?.disable_copy_paste ? 'right-1' : 'left-1', 'w-3 h-3 bg-white rounded-full absolute top-1 shadow-sm transition-all']"></div></div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div> <!-- Close Details Page -->

    <!-- All Questions View -->
    <div v-if="showAllQuestions" class="space-y-6 pb-12 min-w-0 w-full">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-indigo-50 rounded-2xl shadow-sm border border-indigo-100 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
          </div>
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-[24px] font-bold text-slate-800 leading-tight">All Questions</h1>
            </div>
            <div class="text-[13px] text-slate-500 mt-0.5">View all questions in this exam with their details.</div>
          </div>
        </div>
      </div>

      <!-- Breadcrumbs -->
      <div class="flex items-center justify-between bg-white border border-slate-200 rounded-xl px-5 py-3 shadow-sm">
        <div class="flex flex-wrap items-center gap-2 text-[12px] font-bold text-slate-400">
          <span class="hover:text-slate-600 cursor-pointer transition-colors" @click="showDetailsPage = false; showAllQuestions = false">Exams</span>
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          <span class="hover:text-slate-600 cursor-pointer transition-colors" @click="showDetailsPage = false; showAllQuestions = false">Exam List</span>
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          <span class="hover:text-slate-600 cursor-pointer transition-colors" @click="showAllQuestions = false">Exam Details</span>
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          <span class="text-slate-800">All Questions</span>
        </div>
        <button @click="showAllQuestions = false" class="flex items-center gap-2 text-slate-600 font-bold text-[12px] hover:text-[#4338ca] transition-colors whitespace-nowrap">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg> Back to Exam Details
        </button>
      </div>

      <!-- Total Summary -->
      <div class="text-[14px] font-bold text-slate-800 flex items-center gap-2">
        Total Questions: <span class="text-slate-600">{{ selectedExam?.totalQuestions }}</span>
        <span class="text-slate-300">|</span>
        Total Marks: <span class="text-slate-600">{{ selectedExam?.totalMarks }}</span>
      </div>

      <!-- Questions List -->
      <div class="space-y-4">
        
        <!-- Q1 -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-[#4338ca] font-bold text-[14px]">Q1</div>
                <span class="px-3 py-1 bg-indigo-50 text-[#4338ca] text-[11px] font-bold rounded-lg">Multiple Choice</span>
              </div>
              <div class="px-3 py-1 bg-slate-50 text-slate-600 text-[12px] font-bold rounded-lg border border-slate-100">5 Marks</div>
            </div>
            
            <div class="mb-5">
              <p class="text-[12px] font-bold text-slate-500 mb-1">Question</p>
              <p class="text-[14px] font-bold text-slate-800">Which of the following is a characteristic of a relational database?</p>
            </div>
            
            <div class="mb-5">
              <p class="text-[12px] font-bold text-slate-500 mb-2">Options</p>
              <div class="space-y-2">
                <div class="flex items-center gap-3">
                  <div class="w-4 h-4 rounded-full border-[4px] border-[#4338ca] bg-white flex-shrink-0"></div>
                  <span class="text-[13px] text-slate-700 font-bold">A. Data is stored in tables with rows and columns</span>
                </div>
                <div class="flex items-center gap-3">
                  <div class="w-4 h-4 rounded-full border border-slate-300 bg-white flex-shrink-0"></div>
                  <span class="text-[13px] text-slate-700">B. Data is stored as key-value pairs</span>
                </div>
                <div class="flex items-center gap-3">
                  <div class="w-4 h-4 rounded-full border border-slate-300 bg-white flex-shrink-0"></div>
                  <span class="text-[13px] text-slate-700">C. Data is stored in a hierarchical structure</span>
                </div>
                <div class="flex items-center gap-3">
                  <div class="w-4 h-4 rounded-full border border-slate-300 bg-white flex-shrink-0"></div>
                  <span class="text-[13px] text-slate-700">D. Data is stored in documents</span>
                </div>
              </div>
            </div>
            
            <div class="pt-4 border-t border-slate-100">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Instruction</p>
              <p class="text-[13px] text-slate-600">Choose the most appropriate answer from the options given below.</p>
            </div>
          </div>
        </div>

        <!-- Q2 -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-[#4338ca] font-bold text-[14px]">Q2</div>
                <span class="px-3 py-1 bg-indigo-50 text-[#4338ca] text-[11px] font-bold rounded-lg">Multiple Choice</span>
              </div>
              <div class="px-3 py-1 bg-slate-50 text-slate-600 text-[12px] font-bold rounded-lg border border-slate-100">5 Marks</div>
            </div>
            
            <div class="mb-5">
              <p class="text-[12px] font-bold text-slate-500 mb-1">Question</p>
              <p class="text-[14px] font-bold text-slate-800">What is the primary key used for?</p>
            </div>
            
            <div class="mb-5">
              <p class="text-[12px] font-bold text-slate-500 mb-2">Options</p>
              <div class="space-y-2">
                <div class="flex items-center gap-3">
                  <div class="w-4 h-4 rounded-full border-[4px] border-[#4338ca] bg-white flex-shrink-0"></div>
                  <span class="text-[13px] text-slate-700 font-bold">A. To uniquely identify a record in a table</span>
                </div>
                <div class="flex items-center gap-3">
                  <div class="w-4 h-4 rounded-full border border-slate-300 bg-white flex-shrink-0"></div>
                  <span class="text-[13px] text-slate-700">B. To encrypt data in a table</span>
                </div>
                <div class="flex items-center gap-3">
                  <div class="w-4 h-4 rounded-full border border-slate-300 bg-white flex-shrink-0"></div>
                  <span class="text-[13px] text-slate-700">C. To define the relationship between tables</span>
                </div>
                <div class="flex items-center gap-3">
                  <div class="w-4 h-4 rounded-full border border-slate-300 bg-white flex-shrink-0"></div>
                  <span class="text-[13px] text-slate-700">D. To sort data in ascending order</span>
                </div>
              </div>
            </div>
            
            <div class="pt-4 border-t border-slate-100">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Instruction</p>
              <p class="text-[13px] text-slate-600">Choose the correct answer.</p>
            </div>
          </div>
        </div>

        <!-- Q3 -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-[#4338ca] font-bold text-[14px]">Q3</div>
                <span class="px-3 py-1 bg-emerald-50 text-emerald-600 text-[11px] font-bold rounded-lg">True/False</span>
              </div>
              <div class="px-3 py-1 bg-emerald-50 text-emerald-600 text-[12px] font-bold rounded-lg border border-emerald-100">2 Marks</div>
            </div>
            
            <div class="mb-5">
              <p class="text-[12px] font-bold text-slate-500 mb-1">Question</p>
              <p class="text-[14px] font-bold text-slate-800">Normalization is the process of organizing data to reduce redundancy.</p>
            </div>
            
            <div class="mb-5">
              <p class="text-[12px] font-bold text-slate-500 mb-2">Answer</p>
              <div class="flex items-center gap-6">
                <div class="flex items-center gap-2">
                  <div class="w-4 h-4 rounded-full border-[4px] border-[#4338ca] bg-white flex-shrink-0"></div>
                  <span class="text-[13px] font-bold text-slate-700">True</span>
                </div>
                <div class="flex items-center gap-2">
                  <div class="w-4 h-4 rounded-full border border-slate-300 bg-white flex-shrink-0"></div>
                  <span class="text-[13px] text-slate-700">False</span>
                </div>
              </div>
            </div>
            
            <div class="pt-4 border-t border-slate-100">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Instruction</p>
              <p class="text-[13px] text-slate-600">Determine whether the statement is True or False.</p>
            </div>
          </div>
        </div>

        <!-- Q4 -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-[#4338ca] font-bold text-[14px]">Q4</div>
                <span class="px-3 py-1 bg-amber-50 text-amber-600 text-[11px] font-bold rounded-lg">Short Answer</span>
              </div>
              <div class="px-3 py-1 bg-slate-50 text-slate-600 text-[12px] font-bold rounded-lg border border-slate-100">5 Marks</div>
            </div>
            
            <div class="mb-5">
              <p class="text-[12px] font-bold text-slate-500 mb-1">Question</p>
              <p class="text-[14px] font-bold text-slate-800">Explain the difference between DELETE and TRUNCATE statements.</p>
            </div>
            
            <div class="mb-4">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Instruction</p>
              <p class="text-[13px] text-slate-600">Write your answer in the space provided. Your answer should be clear and concise.</p>
            </div>
            
            <div class="inline-block px-3 py-1.5 border border-slate-200 rounded-lg text-[12px] text-slate-500">
              Expected Answer Length: <span class="font-bold text-slate-700">50 - 100 words</span>
            </div>
          </div>
        </div>

        <!-- Q5 -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-[#4338ca] font-bold text-[14px]">Q5</div>
                <span class="px-3 py-1 bg-sky-50 text-sky-600 text-[11px] font-bold rounded-lg">Matching</span>
              </div>
              <div class="px-3 py-1 bg-slate-50 text-slate-600 text-[12px] font-bold rounded-lg border border-slate-100">5 Marks</div>
            </div>
            
            <div class="mb-5">
              <p class="text-[12px] font-bold text-slate-500 mb-1">Question</p>
              <p class="text-[14px] font-bold text-slate-800">Match the SQL clause with its correct description.</p>
            </div>
            
            <div class="grid grid-cols-2 gap-8 mb-6 text-[13px]">
              <div>
                <p class="font-bold text-slate-800 mb-3 border-b border-slate-100 pb-2">SQL Clause</p>
                <div class="space-y-3">
                  <div class="flex gap-3 text-slate-600"><span class="font-bold text-slate-400 w-4">1.</span> WHERE</div>
                  <div class="flex gap-3 text-slate-600"><span class="font-bold text-slate-400 w-4">2.</span> GROUP BY</div>
                  <div class="flex gap-3 text-slate-600"><span class="font-bold text-slate-400 w-4">3.</span> HAVING</div>
                  <div class="flex gap-3 text-slate-600"><span class="font-bold text-slate-400 w-4">4.</span> ORDER BY</div>
                </div>
              </div>
              <div>
                <p class="font-bold text-slate-800 mb-3 border-b border-slate-100 pb-2">Description</p>
                <div class="space-y-3">
                  <div class="flex gap-3 text-slate-600"><span class="font-bold text-slate-400 w-4">A.</span> Filters groups based on a condition</div>
                  <div class="flex gap-3 text-slate-600"><span class="font-bold text-slate-400 w-4">B.</span> Orders the result set</div>
                  <div class="flex gap-3 text-slate-600"><span class="font-bold text-slate-400 w-4">C.</span> Groups rows that have the same values</div>
                  <div class="flex gap-3 text-slate-600"><span class="font-bold text-slate-400 w-4">D.</span> Filters rows based on a condition</div>
                </div>
              </div>
            </div>
            
            <div class="pt-4 border-t border-slate-100">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Instruction</p>
              <p class="text-[13px] text-slate-600">Match each item in the first column with the most appropriate item in the second column.</p>
            </div>
          </div>
        </div>

        <!-- Q6 -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-[#4338ca] font-bold text-[14px]">Q6</div>
                <span class="px-3 py-1 bg-violet-50 text-violet-600 text-[11px] font-bold rounded-lg">Fill in the Blanks</span>
              </div>
              <div class="px-3 py-1 bg-slate-50 text-slate-600 text-[12px] font-bold rounded-lg border border-slate-100">5 Marks</div>
            </div>
            
            <div class="mb-5">
              <p class="text-[12px] font-bold text-slate-500 mb-1">Question</p>
              <p class="text-[14px] font-bold text-slate-800">The ________ clause is used to retrieve specific columns from a table.</p>
            </div>
            
            <div class="mb-4">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Instruction</p>
              <p class="text-[13px] text-slate-600">Fill in the blank with the most appropriate term.</p>
            </div>
            
            <div class="flex items-center gap-3 text-[13px]">
              <span class="font-bold text-slate-700">Expected Answer:</span>
              <span class="px-3 py-1 bg-slate-100 border border-slate-200 rounded text-slate-800 font-mono text-[12px]">SELECT</span>
            </div>
          </div>
        </div>

      </div>
      
      <!-- Pagination -->
      <div class="flex items-center justify-between pt-6 border-t border-slate-200">
        <p class="text-[13px] text-slate-500">Showing <span class="font-bold text-slate-700">1</span> to <span class="font-bold text-slate-700">6</span> of <span class="font-bold text-slate-700">{{ selectedExam?.totalQuestions || 40 }}</span> questions</p>
        <div class="flex gap-1">
          <button class="w-8 h-8 rounded border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 bg-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
          <button class="w-8 h-8 rounded border flex items-center justify-center text-[12px] font-bold bg-[#4338ca] text-white border-[#4338ca]">1</button>
          <button class="w-8 h-8 rounded border border-slate-200 bg-white text-slate-600 text-[12px] font-bold hover:bg-slate-50">2</button>
          <button class="w-8 h-8 rounded border border-slate-200 bg-white text-slate-600 text-[12px] font-bold hover:bg-slate-50">3</button>
          <span class="w-8 h-8 flex items-center justify-center text-slate-400 text-[13px]">...</span>
          <button class="w-8 h-8 rounded border border-slate-200 bg-white text-slate-600 text-[12px] font-bold hover:bg-slate-50">7</button>
          <button class="w-8 h-8 rounded border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 bg-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
        </div>
      </div>
    </div> <!-- Close All Questions View -->

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
          <div class="p-6 text-center">
            <div class="w-14 h-14 bg-rose-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-7 h-7 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
            </div>
            <h3 class="text-[16px] font-bold text-slate-800 mb-2">Delete Exam?</h3>
            <p class="text-[13px] text-slate-500">Delete <span class="font-bold text-slate-700">{{ selectedExam?.title }}</span>? This cannot be undone.</p>
          </div>
          <div class="flex items-center gap-3 px-6 pb-6">
            <button @click="showDeleteModal = false" class="flex-1 py-2.5 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
            <button @click="deleteExam" class="flex-1 py-2.5 text-[13px] font-bold text-white bg-rose-500 hover:bg-rose-600 rounded-xl transition-colors">Delete</button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>
