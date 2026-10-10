<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useInstructorResultStore, type ResultExam } from '../../store/instructorResultStore'

const router = useRouter()
const resultStore = useInstructorResultStore()

// Filter states
const searchQuery = ref('')
const selectedType = ref('All Types')
const selectedStatus = ref('All Status')
const sortBy = ref('Exam Date (Newest)')
const perPage = ref(10)
const currentPage = ref(1)

// Selected exam for quick analytics preview modal
const selectedExamForAnalytics = ref<ResultExam | null>(null)
const showAnalyticsModal = ref(false)

// Gather unique exam types from data
const availableTypes = computed(() => {
  const set = new Set<string>()
  resultStore.results.forEach(r => {
    if (r.type) set.add(r.type)
  })
  return Array.from(set)
})

// Filtered and sorted exams
const filteredResults = computed(() => {
  return resultStore.results.filter(exam => {
    const q = searchQuery.value.trim().toLowerCase()
    const matchesSearch = !q ||
      (exam.title && exam.title.toLowerCase().includes(q)) ||
      (exam.subtitle && exam.subtitle.toLowerCase().includes(q)) ||
      (exam.type && exam.type.toLowerCase().includes(q))

    const matchesType = selectedType.value === 'All Types' || exam.type === selectedType.value
    const matchesStatus = selectedStatus.value === 'All Status' || exam.status === selectedStatus.value

    return matchesSearch && matchesType && matchesStatus
  }).sort((a, b) => {
    if (sortBy.value === 'Title (A-Z)') return a.title.localeCompare(b.title)
    if (sortBy.value === 'Title (Z-A)') return b.title.localeCompare(a.title)
    if (sortBy.value === 'Highest Score') return (b.average_score ?? 0) - (a.average_score ?? 0)
    if (sortBy.value === 'Most Submissions') return (b.submitted_count ?? 0) - (a.submitted_count ?? 0)
    if (sortBy.value === 'Exam Date (Oldest)') return new Date(a.scheduled_at).getTime() - new Date(b.scheduled_at).getTime()
    // Default: Exam Date (Newest)
    return new Date(b.scheduled_at).getTime() - new Date(a.scheduled_at).getTime()
  })
})

// Reset pagination when filters change
watch([searchQuery, selectedType, selectedStatus, sortBy, perPage], () => {
  currentPage.value = 1
})

const isFilterActive = computed(() => {
  return searchQuery.value !== '' ||
    selectedType.value !== 'All Types' ||
    selectedStatus.value !== 'All Status' ||
    sortBy.value !== 'Exam Date (Newest)'
})

const resetFilters = () => {
  searchQuery.value = ''
  selectedType.value = 'All Types'
  selectedStatus.value = 'All Status'
  sortBy.value = 'Exam Date (Newest)'
  currentPage.value = 1
}

// Dynamic Pagination
const totalPages = computed(() => {
  return Math.max(1, Math.ceil(filteredResults.value.length / perPage.value))
})

const paginatedResults = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredResults.value.slice(start, start + perPage.value)
})

const startItemIndex = computed(() => {
  if (filteredResults.value.length === 0) return 0
  return (currentPage.value - 1) * perPage.value + 1
})

const endItemIndex = computed(() => {
  return Math.min(currentPage.value * perPage.value, filteredResults.value.length)
})

const goToPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

// Helpers for visual styling
const getIconClass = (index: number) => {
  const classes = [
    'text-[#4F35F3] bg-indigo-50 border-indigo-100/80',
    'text-emerald-600 bg-emerald-50 border-emerald-100/80',
    'text-amber-600 bg-amber-50 border-amber-100/80',
    'text-sky-600 bg-sky-50 border-sky-100/80'
  ]
  return classes[index % classes.length]
}

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'Published':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200/80'
    case 'Graded':
    case 'Completed':
      return 'bg-blue-50 text-blue-700 border-blue-200/80'
    case 'Pending':
      return 'bg-amber-50 text-amber-700 border-amber-200/80'
    case 'Draft':
      return 'bg-slate-50 text-slate-600 border-slate-200/80'
    default:
      return 'bg-slate-50 text-slate-600 border-slate-200/80'
  }
}

const getStatusDotColor = (status: string) => {
  switch (status) {
    case 'Published':
      return 'bg-emerald-500'
    case 'Graded':
    case 'Completed':
      return 'bg-blue-500'
    case 'Pending':
      return 'bg-amber-500'
    default:
      return 'bg-slate-400'
  }
}

const getTypeBadge = (type: string) => {
  const t = (type || '').toLowerCase()
  if (t.includes('mid')) return 'text-[#4F35F3] bg-indigo-50/80 border-indigo-100'
  if (t.includes('final')) return 'text-blue-700 bg-blue-50/80 border-blue-100'
  if (t.includes('quiz')) return 'text-emerald-700 bg-emerald-50/80 border-emerald-100'
  if (t.includes('assignment')) return 'text-rose-700 bg-rose-50/80 border-rose-100'
  if (t.includes('practical')) return 'text-amber-700 bg-amber-50/80 border-amber-100'
  return 'text-slate-700 bg-slate-50 border-slate-200/80'
}

const openAnalytics = (exam: ResultExam) => {
  selectedExamForAnalytics.value = exam
  showAnalyticsModal.value = true
}

const downloadSingleExamCsv = (exam: ResultExam) => {
  const rows = [
    ['Metric', 'Value'],
    ['Exam Title', exam.title],
    ['Exam Subtitle', exam.subtitle],
    ['Exam Type', exam.type],
    ['Scheduled Date', exam.scheduled_at],
    ['Total Enrolled Students', exam.total_students],
    ['Submitted Count', exam.submitted_count],
    ['Graded Count', exam.graded_count],
    ['Average Score (%)', exam.average_score !== null ? `${exam.average_score}%` : 'N/A'],
    ['Publication Status', exam.is_published ? 'Published' : 'Unpublished'],
    ['Overall Status', exam.status]
  ]
  const csvContent = '\uFEFF' + rows.map(r => `"${r[0]}","${r[1]}"`).join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `Exam_Summary_${exam.title.replace(/[^a-zA-Z0-9]/g, '_')}.csv`
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="bg-white border border-[#E6EBF3] rounded-2xl shadow-xs mb-6 flex flex-col overflow-hidden">
    
    <!-- Unified Search & Filter Toolbar -->
    <div class="p-3.5 sm:p-4 border-b border-[#E6EBF3] flex flex-wrap items-center justify-between gap-3 bg-white">
      
      <div class="flex flex-wrap items-center gap-3 flex-1 min-w-[280px]">
        
        <!-- Search Input -->
        <div class="relative flex-1 min-w-[200px] max-w-md">
          <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input
            v-model="searchQuery"
            type="text"
            class="block w-full pl-9 pr-8 py-2 bg-slate-50/80 hover:bg-slate-50 focus:bg-white border border-[#E6EBF3] rounded-xl text-xs sm:text-sm text-[#17243A] placeholder-[#71819B] focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 focus:border-[#4F35F3] transition-all"
            placeholder="Search exams by title or type..."
          />
          <button
            v-if="searchQuery"
            @click="searchQuery = ''"
            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 cursor-pointer"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
        
        <!-- Exam Type Dropdown -->
        <div class="relative">
          <select
            v-model="selectedType"
            class="appearance-none bg-slate-50/80 hover:bg-slate-50 border border-[#E6EBF3] rounded-xl pl-3.5 pr-8 py-2 text-xs font-semibold text-[#17243A] focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 focus:border-[#4F35F3] transition-all cursor-pointer"
          >
            <option>All Types</option>
            <option v-for="t in availableTypes" :key="t" :value="t">{{ t }}</option>
            <option v-if="!availableTypes.includes('Mid Exam')">Mid Exam</option>
            <option v-if="!availableTypes.includes('Quiz')">Quiz</option>
            <option v-if="!availableTypes.includes('Final Exam')">Final Exam</option>
          </select>
          <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
        </div>
        
        <!-- Status Dropdown -->
        <div class="relative">
          <select
            v-model="selectedStatus"
            class="appearance-none bg-slate-50/80 hover:bg-slate-50 border border-[#E6EBF3] rounded-xl pl-3.5 pr-8 py-2 text-xs font-semibold text-[#17243A] focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 focus:border-[#4F35F3] transition-all cursor-pointer"
          >
            <option>All Status</option>
            <option>Published</option>
            <option>Graded</option>
            <option>Completed</option>
            <option>Pending</option>
            <option>Draft</option>
          </select>
          <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
        </div>
        
        <!-- Sort Dropdown -->
        <div class="relative">
          <select
            v-model="sortBy"
            class="appearance-none bg-slate-50/80 hover:bg-slate-50 border border-[#E6EBF3] rounded-xl pl-3.5 pr-8 py-2 text-xs font-semibold text-[#17243A] focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 focus:border-[#4F35F3] transition-all cursor-pointer"
          >
            <option>Exam Date (Newest)</option>
            <option>Exam Date (Oldest)</option>
            <option>Title (A-Z)</option>
            <option>Highest Score</option>
            <option>Most Submissions</option>
          </select>
          <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
        </div>

        <!-- Reset Filter Button -->
        <button
          v-if="isFilterActive"
          @click="resetFilters"
          class="flex items-center gap-1.5 px-3 py-2 text-[#4F35F3] hover:bg-indigo-50 border border-indigo-100 rounded-xl text-xs font-bold transition-colors cursor-pointer"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
          </svg>
          <span>Reset Filters</span>
        </button>

      </div>

      <!-- Right Metadata & Per Page -->
      <div class="flex items-center gap-3 ml-auto text-xs font-medium text-[#71819B]">
        <div class="hidden xl:flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-50 border border-[#E6EBF3] text-[11px] font-semibold text-slate-600">
          <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
          <span>Semester Timeline</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="text-[11px] text-slate-400">Rows:</span>
          <select
            v-model="perPage"
            class="bg-slate-50 border border-[#E6EBF3] rounded-lg px-2 py-1 text-xs font-bold text-[#17243A] cursor-pointer focus:outline-none"
          >
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
          </select>
        </div>
      </div>

    </div>

    <!-- Desktop Results Table -->
    <div class="hidden md:block overflow-x-auto w-full">
      <table class="w-full text-left border-collapse whitespace-nowrap">
        <thead>
          <tr class="bg-slate-50/70 border-b border-[#E6EBF3] text-[10px] font-bold text-[#71819B] uppercase tracking-wider">
            <th class="py-3.5 pl-6 pr-4">Exam Title</th>
            <th class="py-3.5 px-4 text-center">Exam Type</th>
            <th class="py-3.5 px-4">Exam Date</th>
            <th class="py-3.5 px-4 text-center">Students</th>
            <th class="py-3.5 px-4 text-center">Submitted</th>
            <th class="py-3.5 px-4 text-center">Published</th>
            <th class="py-3.5 px-4 text-center">Status</th>
            <th class="py-3.5 pr-6 pl-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          
          <!-- Loading State Skeletons -->
          <tr v-if="resultStore.isLoading" v-for="i in 4" :key="`skel-${i}`" class="animate-pulse">
            <td class="py-4 pl-6 pr-4">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-slate-100"></div>
                <div class="space-y-1.5">
                  <div class="h-3.5 w-32 bg-slate-100 rounded"></div>
                  <div class="h-2.5 w-20 bg-slate-100 rounded"></div>
                </div>
              </div>
            </td>
            <td class="py-4 px-4 text-center"><div class="h-5 w-16 bg-slate-100 rounded mx-auto"></div></td>
            <td class="py-4 px-4"><div class="h-4 w-28 bg-slate-100 rounded"></div></td>
            <td class="py-4 px-4 text-center"><div class="h-4 w-8 bg-slate-100 rounded mx-auto"></div></td>
            <td class="py-4 px-4 text-center"><div class="h-4 w-14 bg-slate-100 rounded mx-auto"></div></td>
            <td class="py-4 px-4 text-center"><div class="h-4 w-4 bg-slate-100 rounded-full mx-auto"></div></td>
            <td class="py-4 px-4 text-center"><div class="h-5 w-20 bg-slate-100 rounded-full mx-auto"></div></td>
            <td class="py-4 pr-6 pl-4 text-right"><div class="h-7 w-16 bg-slate-100 rounded ml-auto"></div></td>
          </tr>
          
          <!-- Empty State -->
          <tr v-else-if="filteredResults.length === 0">
            <td colspan="8" class="py-16 text-center">
              <div class="max-w-sm mx-auto flex flex-col items-center">
                <div class="w-14 h-14 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-[#4F35F3] mb-3">
                  <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <h3 class="text-sm font-bold text-[#17243A] mb-1">No exam results found</h3>
                <p class="text-xs text-[#71819B] mb-4">
                  {{ isFilterActive ? 'No examination records match your active search or filters.' : 'There are no examination results recorded for your courses yet.' }}
                </p>
                <button
                  v-if="isFilterActive"
                  @click="resetFilters"
                  class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-[#4F35F3] rounded-xl text-xs font-bold transition-colors cursor-pointer"
                >
                  Reset All Filters
                </button>
              </div>
            </td>
          </tr>

          <!-- Data Rows -->
          <tr
            v-else
            v-for="(exam, index) in paginatedResults"
            :key="exam.id"
            class="hover:bg-slate-50/70 transition-colors group"
          >
            <!-- Exam Title -->
            <td class="py-3.5 pl-6 pr-4">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 border shadow-2xs group-hover:scale-105 transition-transform" :class="getIconClass(index)">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div class="flex flex-col min-w-0">
                  <router-link
                    :to="{ name: 'ExamResultDetail', params: { examId: exam.id } }"
                    class="text-xs sm:text-[13px] font-bold text-[#17243A] hover:text-[#4F35F3] transition-colors truncate"
                  >
                    {{ exam.title }}
                  </router-link>
                  <span class="text-[11px] text-[#71819B] font-medium truncate">{{ exam.subtitle || 'Semester Examination' }}</span>
                </div>
              </div>
            </td>
            
            <!-- Exam Type -->
            <td class="py-3.5 px-4 text-center">
              <span class="inline-flex items-center px-2.5 py-0.5 text-[11px] font-bold rounded-lg border shadow-2xs" :class="getTypeBadge(exam.type)">
                {{ exam.type }}
              </span>
            </td>
            
            <!-- Exam Date -->
            <td class="py-3.5 px-4">
              <div class="flex flex-col">
                <span class="text-xs font-bold text-slate-800">
                  {{ new Date(exam.scheduled_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) }}
                </span>
                <span class="text-[11px] text-[#71819B]">
                  {{ new Date(exam.scheduled_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) }}
                </span>
              </div>
            </td>
            
            <!-- Students Count -->
            <td class="py-3.5 px-4 text-center">
              <span class="font-mono text-xs font-bold text-slate-800 bg-slate-50 px-2 py-0.5 rounded-md border border-[#E6EBF3]">
                {{ exam.total_students }}
              </span>
            </td>
            
            <!-- Submitted Count & Percentage -->
            <td class="py-3.5 px-4 text-center">
              <div class="inline-flex flex-col items-center">
                <span class="text-xs font-bold" :class="exam.submitted_count > 0 ? 'text-emerald-600' : 'text-slate-400'">
                  {{ exam.submitted_count }}
                </span>
                <span class="text-[10px] font-semibold text-slate-400">
                  ({{ exam.total_students > 0 ? Math.round((exam.submitted_count / exam.total_students) * 100) : 0 }}%)
                </span>
              </div>
            </td>

            <!-- Published Indicator -->
            <td class="py-3.5 px-4 text-center">
              <span
                v-if="exam.is_published"
                class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-50 text-[#10B981] border border-emerald-200/60 shadow-2xs mx-auto"
                title="Results Published to Students"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
              </span>
              <span
                v-else
                class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 text-slate-400 mx-auto"
                title="Not Yet Published"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
              </span>
            </td>
            
            <!-- Status Pill -->
            <td class="py-3.5 px-4 text-center">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-bold rounded-full border shadow-2xs" :class="getStatusBadge(exam.status)">
                <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotColor(exam.status)"></span>
                {{ exam.status }}
              </span>
            </td>
            
            <!-- Row Actions -->
            <td class="py-3.5 pr-6 pl-4 text-right">
              <div class="flex items-center justify-end gap-1.5 text-slate-400">
                <!-- View Details -->
                <button
                  @click="router.push({ name: 'ExamResultDetail', params: { examId: exam.id } })"
                  class="w-8 h-8 rounded-lg flex items-center justify-center hover:text-[#4F35F3] hover:bg-indigo-50 transition-colors cursor-pointer"
                  title="View Exam Evaluation & Student Marks"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>

                <!-- Analytics Breakdown -->
                <button
                  @click="openAnalytics(exam)"
                  class="w-8 h-8 rounded-lg flex items-center justify-center hover:text-[#0EA5E9] hover:bg-sky-50 transition-colors cursor-pointer"
                  title="Score Breakdown & Metrics"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                  </svg>
                </button>

                <!-- Download CSV -->
                <button
                  @click="downloadSingleExamCsv(exam)"
                  class="w-8 h-8 rounded-lg flex items-center justify-center hover:text-emerald-600 hover:bg-emerald-50 transition-colors cursor-pointer"
                  title="Download Exam CSV"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile Card View (< md) -->
    <div class="md:hidden divide-y divide-slate-100">
      <div v-if="resultStore.isLoading" class="p-8 text-center text-xs text-slate-500">
        <div class="w-8 h-8 border-4 border-indigo-200 border-t-[#4F35F3] rounded-full animate-spin mx-auto mb-3"></div>
        Loading examination results...
      </div>
      <div v-else-if="filteredResults.length === 0" class="p-8 text-center text-slate-400 text-xs">
        No exams match your filters.
      </div>
      <div
        v-else
        v-for="(exam, index) in paginatedResults"
        :key="`m-${exam.id}`"
        class="p-4 space-y-3"
      >
        <div class="flex items-start gap-3">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 border mt-0.5" :class="getIconClass(index)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-xs sm:text-sm font-bold text-[#17243A] truncate">{{ exam.title }}</p>
            <p class="text-[11px] text-[#71819B]">{{ exam.subtitle || 'Semester Examination' }}</p>
          </div>
          <span class="px-2.5 py-1 text-[10px] font-bold rounded-full border shrink-0" :class="getStatusBadge(exam.status)">
            {{ exam.status }}
          </span>
        </div>

        <div class="grid grid-cols-3 gap-2 text-center">
          <div class="bg-slate-50 rounded-xl p-2 border border-[#E6EBF3]">
            <p class="text-[9px] text-[#71819B] font-bold uppercase mb-0.5">Date</p>
            <p class="text-[11px] font-bold text-slate-800">{{ new Date(exam.scheduled_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit' }) }}</p>
          </div>
          <div class="bg-slate-50 rounded-xl p-2 border border-[#E6EBF3]">
            <p class="text-[9px] text-[#71819B] font-bold uppercase mb-0.5">Students</p>
            <p class="text-[11px] font-bold text-slate-800">{{ exam.total_students }}</p>
          </div>
          <div class="bg-slate-50 rounded-xl p-2 border border-[#E6EBF3]">
            <p class="text-[9px] text-[#71819B] font-bold uppercase mb-0.5">Submitted</p>
            <p class="text-[11px] font-bold text-emerald-600">{{ exam.submitted_count }}</p>
          </div>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-slate-100">
          <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-md border" :class="getTypeBadge(exam.type)">
            {{ exam.type }}
          </span>
          <div class="flex items-center gap-2">
            <button
              @click="router.push({ name: 'ExamResultDetail', params: { examId: exam.id } })"
              class="min-h-[44px] px-3 flex items-center justify-center gap-1.5 text-xs font-bold text-[#4F35F3] bg-indigo-50/80 hover:bg-indigo-100 rounded-xl transition-colors cursor-pointer"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
              Details
            </button>
            <button
              @click="openAnalytics(exam)"
              class="min-h-[44px] px-3 flex items-center justify-center gap-1.5 text-xs font-bold text-[#0EA5E9] bg-sky-50/80 hover:bg-sky-100 rounded-xl transition-colors cursor-pointer"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
              Metrics
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Dynamic Pagination Footer -->
    <div class="p-3.5 sm:p-4 border-t border-[#E6EBF3] flex flex-wrap items-center justify-between gap-3 bg-slate-50/40 mt-auto">
      <span class="text-xs text-[#71819B] font-medium">
        Showing <strong class="text-[#17243A] font-bold">{{ startItemIndex }}</strong> to <strong class="text-[#17243A] font-bold">{{ endItemIndex }}</strong> of <strong class="text-[#17243A] font-bold">{{ filteredResults.length }}</strong> exams
      </span>

      <div class="flex items-center gap-1.5">
        <button
          @click="goToPage(currentPage - 1)"
          :disabled="currentPage === 1"
          class="w-8 h-8 flex items-center justify-center rounded-xl border border-[#E6EBF3] bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-800 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shadow-2xs"
          title="Previous Page"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
        </button>

        <button
          v-for="p in totalPages"
          :key="`page-${p}`"
          @click="goToPage(p)"
          class="w-8 h-8 flex items-center justify-center rounded-xl font-bold text-xs transition-colors cursor-pointer shadow-2xs"
          :class="currentPage === p ? 'bg-[#4F35F3] text-white shadow-xs' : 'bg-white border border-[#E6EBF3] text-slate-600 hover:bg-slate-50'"
        >
          {{ p }}
        </button>

        <button
          @click="goToPage(currentPage + 1)"
          :disabled="currentPage === totalPages"
          class="w-8 h-8 flex items-center justify-center rounded-xl border border-[#E6EBF3] bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-800 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shadow-2xs"
          title="Next Page"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
        </button>
      </div>
    </div>

    <!-- Quick Analytics / Metrics Modal -->
    <div
      v-if="showAnalyticsModal && selectedExamForAnalytics"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs"
    >
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 flex flex-col">
        <div class="flex items-center justify-between pb-3.5 border-b border-slate-100">
          <div>
            <h3 class="text-base font-bold text-[#17243A]">Exam Performance Breakdown</h3>
            <p class="text-xs text-[#71819B] mt-0.5">{{ selectedExamForAnalytics.title }}</p>
          </div>
          <button
            @click="showAnalyticsModal = false"
            class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 hover:bg-slate-200 flex items-center justify-center cursor-pointer transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <div class="py-4 space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div class="bg-indigo-50/60 border border-indigo-100 rounded-xl p-3 text-center">
              <span class="block text-[10px] font-bold text-[#71819B] uppercase">Average Score</span>
              <span class="block text-xl font-black text-[#4F35F3] mt-0.5">
                {{ selectedExamForAnalytics.average_score !== null ? `${selectedExamForAnalytics.average_score}%` : 'N/A' }}
              </span>
            </div>
            <div class="bg-emerald-50/60 border border-emerald-100 rounded-xl p-3 text-center">
              <span class="block text-[10px] font-bold text-[#71819B] uppercase">Submission Rate</span>
              <span class="block text-xl font-black text-emerald-600 mt-0.5">
                {{ selectedExamForAnalytics.total_students > 0 ? Math.round((selectedExamForAnalytics.submitted_count / selectedExamForAnalytics.total_students) * 100) : 0 }}%
              </span>
            </div>
          </div>

          <div class="space-y-2 border border-[#E6EBF3] rounded-xl p-3 text-xs divide-y divide-slate-100">
            <div class="flex justify-between py-1">
              <span class="text-[#71819B]">Total Students Enrolled:</span>
              <span class="font-bold text-[#17243A]">{{ selectedExamForAnalytics.total_students }}</span>
            </div>
            <div class="flex justify-between py-1">
              <span class="text-[#71819B]">Submissions Received:</span>
              <span class="font-bold text-emerald-600">{{ selectedExamForAnalytics.submitted_count }}</span>
            </div>
            <div class="flex justify-between py-1">
              <span class="text-[#71819B]">Graded Submissions:</span>
              <span class="font-bold text-[#4F35F3]">{{ selectedExamForAnalytics.graded_count }}</span>
            </div>
            <div class="flex justify-between py-1">
              <span class="text-[#71819B]">Publication Status:</span>
              <span class="font-bold" :class="selectedExamForAnalytics.is_published ? 'text-emerald-600' : 'text-amber-600'">
                {{ selectedExamForAnalytics.is_published ? 'Published to Students' : 'Unpublished' }}
              </span>
            </div>
            <div class="flex justify-between py-1">
              <span class="text-[#71819B]">Exam Type:</span>
              <span class="font-bold text-[#17243A]">{{ selectedExamForAnalytics.type }}</span>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
          <button
            @click="showAnalyticsModal = false"
            class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer"
          >
            Close
          </button>
          <button
            @click="router.push({ name: 'ExamResultDetail', params: { examId: selectedExamForAnalytics.id } }); showAnalyticsModal = false"
            class="px-4 py-2 bg-[#4F35F3] hover:bg-indigo-600 text-white text-xs font-bold rounded-xl transition-colors cursor-pointer flex items-center gap-1.5"
          >
            <span>Open Full Results</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
