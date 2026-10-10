<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useInstructorExamStore } from '../../store/instructorExamStore'
import { useSemesterLockStore } from '../../store/semesterLockStore'

const examStore = useInstructorExamStore()
const lockStore = useSemesterLockStore()

const searchQuery = ref('')
const selectedStatus = ref('All Status')
const sortBy = ref('Exam Date')
const perPage = ref(10)
const currentPage = ref(1)

// Modal state
const showDetailsModal = ref(false)
const selectedExam = ref<any>(null)
const isLoadingDetails = ref(false)

const showDeleteModal = ref(false)
const examToDelete = ref<any | null>(null)
const isDeleting = ref(false)

// Reset to page 1 on filter changes
watch([searchQuery, selectedStatus, sortBy, perPage], () => {
  currentPage.value = 1
})

const confirmDelete = (exam: any) => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('delete exam')
    return
  }
  examToDelete.value = exam
  showDeleteModal.value = true
}

const executeDelete = async () => {
  if (!examToDelete.value) return
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('delete exam')
    showDeleteModal.value = false
    return
  }

  isDeleting.value = true
  try {
    await examStore.deleteExam(examToDelete.value.id)
    showDeleteModal.value = false
    examToDelete.value = null
    if (paginatedExams.value.length === 0 && currentPage.value > 1) {
      currentPage.value--
    }
  } catch (err) {
    console.error('Failed to delete exam:', err)
  } finally {
    isDeleting.value = false
  }
}

const openExamDetails = async (exam: any) => {
  selectedExam.value = exam
  showDetailsModal.value = true
  
  isLoadingDetails.value = true
  try {
    const details = await examStore.fetchExamDetails(exam.id)
    if (details) {
      selectedExam.value = details
    }
  } catch (err) {
    console.error('Failed to load exam details:', err)
  } finally {
    isLoadingDetails.value = false
  }
}

const getDisplayType = (type: string) => {
  let displayType = (type || '').toUpperCase()
  if (type === 'multiple_choice' || type === 'mcq') displayType = 'MULTIPLE CHOICE'
  if (type === 'true_false' || type === 'true/false') displayType = 'TRUE / FALSE'
  if (type === 'short_answer') displayType = 'SHORT ANSWER'
  if (type === 'fill_blank' || type === 'fill_in_the_blank') displayType = 'FILL IN THE BLANK'
  if (type === 'matching' || type === 'Matching') displayType = 'MATCHING'
  if (type === 'essay' || type === 'Essay') displayType = 'ESSAY'
  return displayType
}

const getQuestionIndexWithinType = (q: any) => {
  if (!selectedExam.value?.questions) return 0
  const targetType = getDisplayType(q.type)
  const sameTypeQuestions = selectedExam.value.questions.filter((x: any) => getDisplayType(x.type) === targetType)
  return sameTypeQuestions.findIndex((x: any) => x === q || x.id === q.id) + 1
}

const groupedQuestions = computed(() => {
  if (!selectedExam.value?.questions) return []
  const groups: any[] = []
  selectedExam.value.questions.forEach((q: any) => {
    const displayType = getDisplayType(q.type)

    let typeGroup = groups.find((g: any) => g.questionType === displayType)
    if (!typeGroup) {
      typeGroup = { questionType: displayType, instructionGroups: [] }
      groups.push(typeGroup)
    }

    const instructionStr = q.instruction || ''
    let instGroup = typeGroup.instructionGroups.find((ig: any) => ig.instruction === instructionStr)
    if (!instGroup) {
      instGroup = { instruction: instructionStr, questions: [] }
      typeGroup.instructionGroups.push(instGroup)
    }
    instGroup.questions.push(q)
  })
  return groups
})

const resetFilters = () => {
  searchQuery.value = ''
  selectedStatus.value = 'All Status'
  sortBy.value = 'Exam Date'
  currentPage.value = 1
}

// Format ISO date strings
const formatDate = (iso: string | null | undefined) => {
  if (!iso) return '—'
  try {
    const d = new Date(iso)
    if (isNaN(d.getTime())) return '—'
    return d.toLocaleDateString('en-US', {
      month: 'short',
      day: '2-digit',
      year: 'numeric'
    })
  } catch {
    return '—'
  }
}

const formatTime = (iso: string | null | undefined) => {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    if (isNaN(d.getTime())) return ''
    return d.toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit'
    })
  } catch {
    return ''
  }
}

// Filter and sort exams
const filteredExams = computed(() => {
  let result = [...examStore.exams]

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    result = result.filter(e => 
      e.title?.toLowerCase().includes(q) ||
      (e.course_name && e.course_name.toLowerCase().includes(q)) ||
      (e.course_code && e.course_code.toLowerCase().includes(q))
    )
  }

  if (selectedStatus.value !== 'All Status') {
    const filter = selectedStatus.value.toLowerCase()
    if (filter === 'drafts') {
      result = result.filter(e => e.status?.toLowerCase() === 'draft')
    } else {
      result = result.filter(e => e.status?.toLowerCase() === filter)
    }
  }

  // Sort
  if (sortBy.value === 'Exam Date') {
    result.sort((a, b) => {
      const dateA = a.scheduled_at ? new Date(a.scheduled_at).getTime() : 0
      const dateB = b.scheduled_at ? new Date(b.scheduled_at).getTime() : 0
      return dateB - dateA
    })
  } else if (sortBy.value === 'Name (A-Z)') {
    result.sort((a, b) => (a.title || '').localeCompare(b.title || ''))
  }

  return result
})

// Pagination calculations
const totalPages = computed(() => {
  return Math.max(1, Math.ceil(filteredExams.value.length / perPage.value))
})

const paginatedExams = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredExams.value.slice(start, start + perPage.value)
})

const paginationStart = computed(() => {
  if (filteredExams.value.length === 0) return 0
  return (currentPage.value - 1) * perPage.value + 1
})

const paginationEnd = computed(() => {
  return Math.min(filteredExams.value.length, currentPage.value * perPage.value)
})

const goToPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

const getStatusBadge = (status: string | undefined) => {
  const s = (status || '').toLowerCase()
  switch (s) {
    case 'upcoming':
    case 'scheduled':
      return {
        label: 'Upcoming',
        class: 'bg-indigo-50 text-[#5138ed] border border-indigo-100',
        dot: 'bg-[#5138ed]'
      }
    case 'active':
      return {
        label: 'Active',
        class: 'bg-blue-50 text-blue-700 border border-blue-100',
        dot: 'bg-blue-600 animate-pulse'
      }
    case 'published':
      return {
        label: 'Published',
        class: 'bg-emerald-50 text-emerald-700 border border-emerald-100',
        dot: 'bg-emerald-600'
      }
    case 'completed':
      return {
        label: 'Completed',
        class: 'bg-amber-50 text-amber-700 border border-amber-100',
        dot: 'bg-amber-600'
      }
    case 'draft':
      return {
        label: 'Draft',
        class: 'bg-rose-50 text-rose-700 border border-rose-100',
        dot: 'bg-rose-500'
      }
    case 'archived':
      return {
        label: 'Archived',
        class: 'bg-slate-100 text-slate-700 border border-slate-200',
        dot: 'bg-slate-500'
      }
    default:
      return {
        label: status || 'General',
        class: 'bg-slate-100 text-slate-700 border border-slate-200',
        dot: 'bg-slate-400'
      }
  }
}

// Export CSV functionality
const exportToCsv = () => {
  const data = filteredExams.value
  if (!data.length) return

  const headers = ['ID', 'Exam Title', 'Course Code', 'Course Name', 'Status', 'Date', 'Duration (min)', 'Total Marks', 'Questions Count']
  const rows = data.map(e => [
    e.id,
    `"${(e.title || '').replace(/"/g, '""')}"`,
    `"${e.course_code || ''}"`,
    `"${(e.course_name || '').replace(/"/g, '""')}"`,
    e.status || '',
    e.scheduled_at ? new Date(e.scheduled_at).toLocaleString() : '',
    e.duration_minutes || '',
    e.total_marks || '',
    e.questions_count ?? ''
  ])

  const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers.join(','), ...rows.map(r => r.join(','))].join('\n')
  const encodedUri = encodeURI(csvContent)
  const link = document.createElement('a')
  link.setAttribute('href', encodedUri)
  link.setAttribute('download', `wollo_exams_export_${new Date().toISOString().slice(0, 10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

defineExpose({
  exportToCsv
})
</script>

<template>
  <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
    
    <!-- Filter & Search Toolbar -->
    <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center justify-between gap-3 sm:gap-4 bg-slate-50/50">
      
      <!-- Search Input -->
      <div class="relative flex-1 max-w-md">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <input 
          v-model="searchQuery"
          type="text" 
          class="block w-full pl-10 pr-9 py-2.5 min-h-[44px] bg-white border border-slate-200 rounded-xl text-xs sm:text-[13px] text-slate-800 placeholder-slate-400 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors"
          placeholder="Search exams by title, course, or code..."
        >
        <button 
          v-if="searchQuery"
          @click="searchQuery = ''"
          class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
          title="Clear search"
          aria-label="Clear search"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Filters & Selectors -->
      <div class="flex flex-wrap items-center justify-between lg:justify-end gap-2.5">
        
        <!-- Status Filter -->
        <div class="flex items-center gap-1.5">
          <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider hidden sm:inline">Status</label>
          <div class="relative">
            <select 
              v-model="selectedStatus" 
              class="appearance-none min-h-[40px] bg-white border border-slate-200 rounded-xl text-xs font-semibold pl-3 pr-8 py-2 text-slate-700 focus:outline-none focus:border-[#5138ed]"
            >
              <option>All Status</option>
              <option value="Published">Published</option>
              <option value="Upcoming">Upcoming</option>
              <option value="Active">Active</option>
              <option value="Completed">Completed</option>
              <option value="Drafts">Drafts</option>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400">
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
          </div>
        </div>

        <!-- Sort Filter -->
        <div class="flex items-center gap-1.5">
          <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider hidden sm:inline">Sort By</label>
          <div class="relative">
            <select 
              v-model="sortBy" 
              class="appearance-none min-h-[40px] bg-white border border-slate-200 rounded-xl text-xs font-semibold pl-3 pr-8 py-2 text-slate-700 focus:outline-none focus:border-[#5138ed]"
            >
              <option>Exam Date</option>
              <option>Name (A-Z)</option>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400">
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
          </div>
        </div>

        <!-- Items Per Page -->
        <div class="flex items-center gap-1.5 pl-2 border-l border-slate-200">
          <span class="text-xs text-slate-400 hidden xl:inline">Show</span>
          <select 
            v-model="perPage" 
            class="text-xs bg-white border border-slate-200 text-slate-700 rounded-lg px-2 py-1.5 min-h-[36px] focus:outline-none focus:border-[#5138ed]"
            aria-label="Select exams per page"
          >
            <option :value="5">5</option>
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
          </select>
        </div>

        <!-- Reset Filters Button -->
        <button 
          v-if="searchQuery || selectedStatus !== 'All Status' || sortBy !== 'Exam Date'"
          @click="resetFilters"
          class="px-2.5 py-1.5 text-xs text-[#5138ed] hover:bg-indigo-50 rounded-lg font-bold transition-colors"
          title="Reset all filters"
        >
          Reset
        </button>

      </div>

    </div>

    <!-- Desktop Table View -->
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider bg-slate-50/40">
            <th class="py-3.5 pl-6 pr-4 w-[28%]">Exam Title</th>
            <th class="py-3.5 px-4 w-[20%]">Date & Time</th>
            <th class="py-3.5 px-4 w-[12%] text-center">Duration</th>
            <th class="py-3.5 px-4 w-[10%] text-center">Marks</th>
            <th class="py-3.5 px-4 w-[10%] text-center">Questions</th>
            <th class="py-3.5 px-4 w-[10%] text-center">Status</th>
            <th class="py-3.5 pl-4 pr-6 text-right w-[10%]">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100/80">
          
          <!-- Loading State -->
          <template v-if="examStore.isLoading">
            <tr v-for="i in 3" :key="'skeleton-' + i" class="animate-pulse">
              <td class="py-4 pl-6 pr-4">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl bg-slate-100 shrink-0"></div>
                  <div class="space-y-1.5 flex-1">
                    <div class="h-4 bg-slate-100 rounded w-3/4"></div>
                    <div class="h-3 bg-slate-100 rounded w-1/3"></div>
                  </div>
                </div>
              </td>
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-100 rounded w-4/5"></div></td>
              <td class="py-4 px-4 text-center"><div class="h-4 bg-slate-100 rounded w-12 mx-auto"></div></td>
              <td class="py-4 px-4 text-center"><div class="h-4 bg-slate-100 rounded w-8 mx-auto"></div></td>
              <td class="py-4 px-4 text-center"><div class="h-4 bg-slate-100 rounded w-8 mx-auto"></div></td>
              <td class="py-4 px-4 text-center"><div class="h-5 bg-slate-100 rounded-full w-16 mx-auto"></div></td>
              <td class="py-4 pl-4 pr-6 text-right"><div class="h-8 bg-slate-100 rounded-lg w-16 ml-auto"></div></td>
            </tr>
          </template>

          <!-- Search Empty State -->
          <tr v-else-if="filteredExams.length === 0 && (searchQuery || selectedStatus !== 'All Status')">
            <td colspan="7" class="py-12 text-center">
              <div class="max-w-sm mx-auto flex flex-col items-center">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center mb-3">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800 mb-1">No exams match your search</h4>
                <p class="text-xs text-slate-500 mb-4 text-center">
                  We couldn't find any exams matching your criteria. Try adjusting or clearing your filters.
                </p>
                <button 
                  @click="resetFilters"
                  class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors min-h-[38px]"
                >
                  Clear Filters
                </button>
              </div>
            </td>
          </tr>

          <!-- Zero Exams Empty State -->
          <tr v-else-if="examStore.exams.length === 0">
            <td colspan="7" class="py-14 text-center">
              <div class="max-w-md mx-auto flex flex-col items-center">
                <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-[#5138ed] flex items-center justify-center mb-3.5 shadow-2xs">
                  <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <h4 class="text-base font-bold text-slate-900 mb-1">No Examinations Found</h4>
                <p class="text-xs text-slate-500 mb-4 text-center max-w-sm">
                  You haven't scheduled or created any exams for your courses yet.
                </p>
                <router-link
                  v-if="!lockStore.isLocked"
                  to="/instructor/exams/create"
                  class="px-5 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white rounded-xl text-xs font-bold shadow-xs transition-colors min-h-[40px] flex items-center gap-2"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  Create New Exam
                </router-link>
              </div>
            </td>
          </tr>

          <!-- Data Rows -->
          <tr 
            v-else 
            v-for="exam in paginatedExams" 
            :key="exam.id" 
            class="hover:bg-slate-50/70 transition-colors group"
          >
            <!-- Exam Title & Icon -->
            <td class="py-4 pl-6 pr-4">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-50/80 border border-indigo-100/70 flex items-center justify-center shrink-0 text-[#5138ed] shadow-2xs group-hover:scale-105 transition-transform">
                  <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div class="min-w-0 flex-1">
                  <button 
                    @click="openExamDetails(exam)"
                    class="text-[13px] font-bold text-slate-800 hover:text-[#5138ed] transition-colors truncate block text-left w-full cursor-pointer"
                    :title="exam.title"
                  >
                    {{ exam.title }}
                  </button>
                  <span class="text-[11px] text-slate-400 block truncate">
                    {{ exam.course_name || exam.course_code || 'General Course' }}
                  </span>
                </div>
              </div>
            </td>

            <!-- Date & Time -->
            <td class="py-4 px-4">
              <div class="flex flex-col">
                <span class="text-[13px] font-semibold text-slate-700 flex items-center gap-1.5">
                  <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  {{ formatDate(exam.scheduled_at) }}
                </span>
                <span v-if="formatTime(exam.scheduled_at)" class="text-[11px] text-slate-400 pl-5">
                  {{ formatTime(exam.scheduled_at) }}
                </span>
              </div>
            </td>

            <!-- Duration -->
            <td class="py-4 px-4 text-center">
              <span class="inline-flex items-center gap-1 text-[13px] text-slate-600 font-medium">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ exam.duration_minutes }} min
              </span>
            </td>

            <!-- Marks -->
            <td class="py-4 px-4 text-center">
              <span class="text-[13px] font-bold text-slate-700">
                {{ exam.total_marks }}
              </span>
            </td>

            <!-- Questions -->
            <td class="py-4 px-4 text-center">
              <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                {{ exam.questions_count !== undefined ? exam.questions_count : '-' }}
              </span>
            </td>

            <!-- Status -->
            <td class="py-4 px-4 text-center">
              <span 
                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-bold rounded-lg capitalize"
                :class="getStatusBadge(exam.status).class"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="getStatusBadge(exam.status).dot"></span>
                {{ getStatusBadge(exam.status).label }}
              </span>
            </td>

            <!-- Actions -->
            <td class="py-4 pl-4 pr-6 text-right">
              <div class="flex items-center justify-end gap-1.5">
                
                <!-- View Exam Details -->
                <button 
                  @click="openExamDetails(exam)" 
                  class="w-8 h-8 flex items-center justify-center border border-slate-200 text-slate-600 hover:text-[#5138ed] hover:border-indigo-200 hover:bg-indigo-50/50 rounded-lg transition-colors min-h-[34px] min-w-[34px]" 
                  title="View Exam Details"
                  aria-label="View Exam Details"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>

                <!-- Unlocked Mode Actions -->
                <template v-if="!lockStore.isLocked">
                  <!-- Edit Exam -->
                  <router-link 
                    :to="`/instructor/exams/edit/${exam.id}`" 
                    class="w-8 h-8 flex items-center justify-center border border-slate-200 text-slate-500 hover:text-slate-800 hover:bg-slate-50 hover:border-slate-300 rounded-lg transition-colors min-h-[34px] min-w-[34px]" 
                    title="Edit Exam"
                    aria-label="Edit Exam"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                  </router-link>

                  <!-- Add Questions -->
                  <router-link 
                    :to="`/instructor/exams/edit/${exam.id}?step=2`" 
                    class="w-8 h-8 flex items-center justify-center border border-slate-200 text-[#5138ed] hover:bg-indigo-50 hover:border-indigo-200 rounded-lg transition-colors min-h-[34px] min-w-[34px]" 
                    title="Add Questions to Exam"
                    aria-label="Add Questions"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                  </router-link>

                  <!-- Delete Exam -->
                  <button 
                    @click="confirmDelete(exam)" 
                    :disabled="examStore.isSaving" 
                    class="w-8 h-8 flex items-center justify-center border border-slate-200 text-rose-500 hover:bg-rose-50 hover:border-rose-200 rounded-lg transition-colors min-h-[34px] min-w-[34px] disabled:opacity-50" 
                    title="Delete Exam"
                    aria-label="Delete Exam"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </template>

                <!-- Locked Mode Indicator -->
                <template v-else>
                  <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-1 rounded border border-emerald-200/70 inline-flex items-center gap-1">
                    <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Locked
                  </span>
                </template>

              </div>
            </td>
          </tr>

        </tbody>
      </table>
    </div>

    <!-- Mobile Card View -->
    <div class="md:hidden p-4 space-y-3">
      <!-- Loading Skeleton -->
      <template v-if="examStore.isLoading">
        <div v-for="i in 3" :key="'mob-skel-' + i" class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 animate-pulse space-y-3">
          <div class="h-4 bg-slate-200 rounded w-1/2"></div>
          <div class="h-3 bg-slate-100 rounded w-3/4"></div>
          <div class="h-8 bg-slate-100 rounded"></div>
        </div>
      </template>

      <!-- Empty State Mobile -->
      <div v-else-if="filteredExams.length === 0" class="text-center py-8">
        <p class="text-sm font-bold text-slate-800">No exams found</p>
        <p class="text-xs text-slate-500 mt-1 mb-3">Try adjusting your search criteria or reset filters.</p>
        <button @click="resetFilters" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold">
          Clear Filters
        </button>
      </div>

      <!-- Mobile Data Cards -->
      <div 
        v-else 
        v-for="exam in paginatedExams" 
        :key="'mob-' + exam.id"
        class="p-4 rounded-xl border border-slate-200/80 bg-white hover:border-slate-300 transition-colors space-y-3 shadow-2xs"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0 text-[#5138ed]">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div class="min-w-0">
              <h3 
                @click="openExamDetails(exam)"
                class="text-sm font-bold text-slate-900 leading-snug truncate cursor-pointer hover:text-[#5138ed]"
              >
                {{ exam.title }}
              </h3>
              <span class="text-[11px] text-slate-400 block truncate">
                {{ exam.course_name || exam.course_code || 'General Course' }}
              </span>
            </div>
          </div>
          <span 
            class="px-2 py-0.5 text-[10px] font-bold rounded shrink-0 capitalize"
            :class="getStatusBadge(exam.status).class"
          >
            {{ getStatusBadge(exam.status).label }}
          </span>
        </div>

        <!-- Meta Grid -->
        <div class="grid grid-cols-3 gap-2 text-center py-1">
          <div class="bg-slate-50 rounded-lg p-2">
            <p class="text-[10px] text-slate-400 font-semibold mb-0.5">Date</p>
            <p class="text-[11px] font-bold text-slate-700 truncate">{{ formatDate(exam.scheduled_at) }}</p>
          </div>
          <div class="bg-slate-50 rounded-lg p-2">
            <p class="text-[10px] text-slate-400 font-semibold mb-0.5">Duration</p>
            <p class="text-[11px] font-bold text-slate-700">{{ exam.duration_minutes }}m</p>
          </div>
          <div class="bg-slate-50 rounded-lg p-2">
            <p class="text-[10px] text-slate-400 font-semibold mb-0.5">Marks</p>
            <p class="text-[11px] font-bold text-slate-700">{{ exam.total_marks }}</p>
          </div>
        </div>

        <!-- Mobile Actions -->
        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100">
          <button 
            @click="openExamDetails(exam)"
            class="flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-bold text-[#5138ed] bg-indigo-50 border border-indigo-100 rounded-xl hover:bg-indigo-100 transition-colors min-h-[44px]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            View
          </button>
          
          <template v-if="!lockStore.isLocked">
            <router-link 
              :to="`/instructor/exams/edit/${exam.id}`"
              class="flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors min-h-[44px]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
              </svg>
              Edit
            </router-link>
          </template>
          <template v-else>
            <span class="flex items-center justify-center text-[10px] font-bold text-emerald-700 bg-emerald-50 rounded-xl border border-emerald-200 min-h-[44px]">
              Semester Locked
            </span>
          </template>
        </div>
      </div>
    </div>
    
    <!-- Real Dynamic Pagination -->
    <div 
      v-if="filteredExams.length > 0"
      class="p-4 sm:px-6 sm:py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/40"
    >
      <span class="text-xs sm:text-[13px] text-slate-500 font-medium text-center sm:text-left">
        Showing <span class="font-bold text-slate-800">{{ paginationStart }}</span> to <span class="font-bold text-slate-800">{{ paginationEnd }}</span> of <span class="font-bold text-slate-800">{{ filteredExams.length }}</span> exams
      </span>
      
      <div class="flex items-center gap-1.5">
        <!-- Previous Page Button -->
        <button 
          @click="goToPage(currentPage - 1)"
          :disabled="currentPage === 1"
          class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-xl border border-slate-200 text-slate-600 hover:bg-white hover:border-slate-300 disabled:opacity-40 disabled:pointer-events-none transition-colors min-h-[36px] min-w-[36px]"
          title="Previous Page"
          aria-label="Previous Page"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>

        <!-- Page Numbers -->
        <template v-for="page in totalPages" :key="'page-' + page">
          <button 
            @click="goToPage(page)"
            class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-xl font-bold text-xs sm:text-sm transition-colors min-h-[36px] min-w-[36px]"
            :class="currentPage === page ? 'bg-[#5138ed] text-white shadow-xs' : 'border border-slate-200 text-slate-600 hover:bg-white hover:border-slate-300'"
            :aria-label="'Page ' + page"
            :aria-current="currentPage === page ? 'page' : undefined"
          >
            {{ page }}
          </button>
        </template>

        <!-- Next Page Button -->
        <button 
          @click="goToPage(currentPage + 1)"
          :disabled="currentPage === totalPages"
          class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-xl border border-slate-200 text-slate-600 hover:bg-white hover:border-slate-300 disabled:opacity-40 disabled:pointer-events-none transition-colors min-h-[36px] min-w-[36px]"
          title="Next Page"
          aria-label="Next Page"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </div>

  </div>

  <!-- Exam Details Modal -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div 
        v-if="showDetailsModal" 
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" 
        @click.self="showDetailsModal = false"
        @keydown.escape="showDetailsModal = false"
      >
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh] border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
          
          <!-- Header -->
          <div class="px-6 py-4.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 text-[#5138ed] flex items-center justify-center shadow-2xs">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <div>
                <h3 class="text-base font-bold text-slate-900 leading-tight">
                  {{ selectedExam?.title || 'Exam Details' }}
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ selectedExam?.course_code }} • {{ selectedExam?.course_name }}
                </p>
              </div>
            </div>
            <button 
              @click="showDetailsModal = false" 
              class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors"
              aria-label="Close dialog"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 overflow-y-auto bg-slate-50/50 space-y-5">
            <!-- Loading overlay -->
            <div v-if="isLoadingDetails" class="py-12 flex flex-col items-center justify-center gap-3">
              <svg class="w-8 h-8 text-[#5138ed] animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span class="text-xs font-bold text-slate-500">Loading exam structure...</span>
            </div>

            <template v-else>
              <!-- Info Cards Grid -->
              <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-2xs">
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                  <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Exam Information
                </h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                  <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Status</p>
                    <span 
                      class="px-2.5 py-1 text-[11px] font-bold rounded-lg capitalize inline-block" 
                      :class="getStatusBadge(selectedExam?.status).class"
                    >
                      {{ getStatusBadge(selectedExam?.status).label }}
                    </span>
                  </div>
                  <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Date & Time</p>
                    <p class="text-xs font-bold text-slate-800">
                      {{ formatDate(selectedExam?.scheduled_at) }}
                      <span class="text-slate-400 font-normal block">{{ formatTime(selectedExam?.scheduled_at) }}</span>
                    </p>
                  </div>
                  <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Duration</p>
                    <p class="text-xs font-bold text-slate-800">{{ selectedExam?.duration_minutes }} Minutes</p>
                  </div>
                  <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Marks</p>
                    <p class="text-xs font-bold text-slate-800">{{ selectedExam?.total_marks }} Points</p>
                  </div>
                  <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Questions</p>
                    <p class="text-xs font-bold text-slate-800">{{ selectedExam?.questions_count !== undefined ? selectedExam?.questions_count : (selectedExam?.questions?.length ?? '-') }} Questions</p>
                  </div>
                  <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Enrolled Students</p>
                    <p class="text-xs font-bold text-slate-800">{{ selectedExam?.students_count !== undefined ? selectedExam?.students_count : '-' }} Students</p>
                  </div>
                </div>
              </div>

              <!-- Settings & Security Summary -->
              <div v-if="selectedExam?.settings" class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-2xs">
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                  <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                  Security & Examination Settings
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                  <div class="px-3.5 py-2.5 bg-slate-50 rounded-xl flex justify-between items-center border border-slate-100">
                    <span class="text-xs font-medium text-slate-600">Shuffle Questions</span>
                    <span class="text-xs font-bold" :class="selectedExam.settings.shuffle_questions ? 'text-emerald-700' : 'text-slate-400'">
                      {{ selectedExam.settings.shuffle_questions ? 'Enabled' : 'Disabled' }}
                    </span>
                  </div>
                  <div class="px-3.5 py-2.5 bg-slate-50 rounded-xl flex justify-between items-center border border-slate-100">
                    <span class="text-xs font-medium text-slate-600">Allow Calculator</span>
                    <span class="text-xs font-bold" :class="selectedExam.settings.allow_calculator ? 'text-emerald-700' : 'text-slate-400'">
                      {{ selectedExam.settings.allow_calculator ? 'Enabled' : 'Disabled' }}
                    </span>
                  </div>
                  <div class="px-3.5 py-2.5 bg-slate-50 rounded-xl flex justify-between items-center border border-slate-100">
                    <span class="text-xs font-medium text-slate-600">Tab Monitoring</span>
                    <span class="text-xs font-bold" :class="selectedExam.settings.enable_browser_tab_monitoring ? 'text-emerald-700' : 'text-slate-400'">
                      {{ selectedExam.settings.enable_browser_tab_monitoring ? 'Enabled' : 'Disabled' }}
                    </span>
                  </div>
                  <div class="px-3.5 py-2.5 bg-slate-50 rounded-xl flex justify-between items-center border border-slate-100">
                    <span class="text-xs font-medium text-slate-600">Release Results</span>
                    <span class="text-xs font-bold" :class="selectedExam.settings.show_results_after ? 'text-emerald-700' : 'text-slate-400'">
                      {{ selectedExam.settings.show_results_after ? 'Enabled' : 'Disabled' }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Questions List -->
              <div v-if="selectedExam?.questions && selectedExam.questions.length > 0" class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-2xs">
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                  <span>Questions in Examination ({{ selectedExam.questions.length }})</span>
                </h4>
                
                <div class="space-y-6">
                  <div v-for="typeGroup in groupedQuestions" :key="typeGroup.questionType">
                    <div class="mb-3 border-b border-slate-100 pb-2">
                      <h5 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-2">
                        <span class="w-1.5 h-4 bg-[#5138ed] rounded-full inline-block"></span>
                        {{ typeGroup.questionType }}
                      </h5>
                    </div>

                    <div class="space-y-4 pl-3 border-l-2 border-indigo-100">
                      <div v-for="(instGroup, instIdx) in typeGroup.instructionGroups" :key="instIdx">
                        <p v-if="instGroup.instruction" class="text-xs font-semibold text-slate-600 italic mb-2">
                          "{{ instGroup.instruction }}"
                        </p>
                        
                        <div class="space-y-2.5">
                          <div 
                            v-for="(q, qIdx) in instGroup.questions" 
                            :key="q.id || qIdx"
                            class="p-3.5 bg-slate-50 border border-slate-200/60 rounded-xl text-xs space-y-2"
                          >
                            <div class="flex items-center justify-between gap-2">
                              <span class="font-bold text-[#5138ed] bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">
                                Q{{ getQuestionIndexWithinType(q) }}
                              </span>
                              <span class="font-bold text-slate-500 bg-white px-2 py-0.5 rounded border border-slate-200">
                                {{ q.marks || 1 }} Marks
                              </span>
                            </div>
                            <p class="text-slate-800 font-medium leading-relaxed" v-html="q.text"></p>
                            
                            <div v-if="q.options && q.options.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 pt-1">
                              <div 
                                v-for="(opt, oIdx) in q.options" 
                                :key="oIdx"
                                class="p-2 rounded-lg bg-white border border-slate-200/70 text-slate-600 flex items-start gap-1.5"
                              >
                                <span class="font-bold text-slate-400">{{ String.fromCharCode(65 + Number(oIdx)) }}.</span>
                                <span v-html="typeof opt === 'string' ? opt : (opt.text || opt)"></span>
                              </div>
                            </div>

                            <div v-if="q.correct_answer" class="pt-1 text-[11px] font-semibold text-emerald-700">
                              Correct: <span class="bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">{{ q.correct_answer }}</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Zero Questions in Exam -->
              <div v-else class="bg-white p-8 rounded-2xl border border-slate-200/80 shadow-2xs text-center">
                <p class="text-xs font-bold text-slate-500">No questions attached to this exam yet.</p>
              </div>
            </template>
          </div>
          
          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-end gap-3">
            <button 
              @click="showDetailsModal = false" 
              class="px-5 py-2.5 border border-slate-200 text-slate-600 font-bold text-xs rounded-xl hover:bg-slate-100 transition-colors min-h-[42px]"
            >
              Close
            </button>
            <router-link 
              v-if="!lockStore.isLocked"
              :to="`/instructor/exams/edit/${selectedExam?.id}`" 
              class="px-5 py-2.5 bg-[#5138ed] text-white font-bold text-xs rounded-xl hover:bg-indigo-600 transition-colors shadow-xs flex items-center gap-2 min-h-[42px]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
              </svg>
              Edit Exam
            </router-link>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Clean Delete Confirmation Modal -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div 
        v-if="showDeleteModal && examToDelete" 
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" 
        @keydown.escape="showDeleteModal = false"
      >
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
          <div class="p-6">
            <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </div>
            <h3 class="text-base font-bold text-slate-900 mb-1">Delete Exam?</h3>
            <p class="text-xs sm:text-[13px] text-slate-600 mb-2">
              Are you sure you want to delete <strong class="text-slate-800 font-semibold">"{{ examToDelete.title }}"</strong>?
            </p>
            <p class="text-xs text-rose-600 bg-rose-50 p-3 rounded-xl border border-rose-100 mb-4">
              This action cannot be undone and will permanently remove this examination record.
            </p>
            
            <div class="flex items-center justify-end gap-3 pt-2">
              <button 
                @click="showDeleteModal = false" 
                class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors min-h-[42px]"
              >
                Cancel
              </button>
              <button 
                @click="executeDelete" 
                :disabled="isDeleting" 
                class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-xs transition-colors flex items-center justify-center gap-2 min-h-[42px] disabled:opacity-50"
              >
                <svg v-if="isDeleting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ isDeleting ? 'Deleting...' : 'Delete Exam' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
