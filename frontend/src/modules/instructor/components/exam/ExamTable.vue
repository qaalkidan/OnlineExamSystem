<script setup lang="ts">
import { ref, computed } from 'vue'
import { useInstructorExamStore } from '../../store/instructorExamStore'
import { useSemesterLockStore } from '../../store/semesterLockStore'

const examStore = useInstructorExamStore()
const lockStore = useSemesterLockStore()

const searchQuery = ref('')
const selectedStatus = ref('All Status')
const selectedSemester = ref('Semester I, 2025/2026')
const sortBy = ref('Exam Date')

// Modal state
const showDetailsModal = ref(false)
const selectedExam = ref<any>(null)
const isLoadingDetails = ref(false)

const showDeleteModal = ref(false)
const examToDelete = ref<number | null>(null)
const isDeleting = ref(false)

const confirmDelete = (id: number) => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('delete exam')
    return
  }
  examToDelete.value = id
  showDeleteModal.value = true
}

const executeDelete = async () => {
  if (examToDelete.value) {
    isDeleting.value = true
    try {
      await examStore.deleteExam(examToDelete.value)
    } finally {
      isDeleting.value = false
      showDeleteModal.value = false
      examToDelete.value = null
    }
  }
}

const openExamDetails = async (exam: any) => {
  selectedExam.value = exam
  showDetailsModal.value = true
  
  isLoadingDetails.value = true
  const details = await examStore.fetchExamDetails(exam.id)
  if (details) {
    selectedExam.value = details
  }
  isLoadingDetails.value = false
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
    let displayType = getDisplayType(q.type)

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
  selectedSemester.value = 'Semester I, 2025/2026'
  sortBy.value = 'Exam Date'
}

// Format ISO date strings
const formatDate = (iso: string | null) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-US', {
    month: 'short', day: '2-digit', year: 'numeric'
  })
}
const formatTime = (iso: string | null) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('en-US', {
    hour: '2-digit', minute: '2-digit'
  })
}

// Compute filtered exams
const filteredExams = computed(() => {
  let result = [...examStore.exams]

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(e => e.title.toLowerCase().includes(q) || (e.course_name && e.course_name.toLowerCase().includes(q)) || (e.course_code && e.course_code.toLowerCase().includes(q)))
  }

  if (selectedStatus.value !== 'All Status') {
    result = result.filter(e => e.status.toLowerCase() === selectedStatus.value.toLowerCase())
  }

  // Sort By
  if (sortBy.value === 'Exam Date') {
    result.sort((a, b) => {
      const dateA = a.scheduled_at ? new Date(a.scheduled_at).getTime() : 0
      const dateB = b.scheduled_at ? new Date(b.scheduled_at).getTime() : 0
      return dateB - dateA
    })
  } else if (sortBy.value === 'Name (A-Z)') {
    result.sort((a, b) => a.title.localeCompare(b.title))
  }

  return result
})

const getStatusColor = (status: string) => {
  switch(status.toLowerCase()) {
    case 'upcoming': 
    case 'scheduled': 
      return 'bg-indigo-50 text-[#5138ed]'
    case 'active': 
    case 'published': 
    case 'completed': 
      return 'bg-emerald-50 text-emerald-600'
    case 'draft': 
      return 'bg-amber-50 text-amber-600'
    case 'archived':
      return 'bg-slate-100 text-slate-600'
    default: 
      return 'bg-emerald-50 text-emerald-600'
  }
}

const iconStyles = [
  { bg: 'bg-indigo-50', text: 'text-indigo-500', svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>' },
  { bg: 'bg-emerald-50', text: 'text-emerald-500', svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>' },
  { bg: 'bg-indigo-50', text: 'text-[#5138ed]', svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>' },
  { bg: 'bg-amber-50', text: 'text-amber-500', svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>' },
  { bg: 'bg-emerald-50', text: 'text-emerald-500', svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>' },
  { bg: 'bg-sky-50', text: 'text-sky-500', svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>' },
  { bg: 'bg-rose-50', text: 'text-rose-500', svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>' },
  { bg: 'bg-amber-50', text: 'text-amber-500', svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>' },
  { bg: 'bg-slate-100', text: 'text-slate-500', svg: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>' }
]
</script>

<template>
  <div class="p-4 sm:p-6">
    
    <!-- Filter Bar -->
    <div class="flex flex-wrap items-end gap-3 mb-6 w-full">
      
      <!-- Search -->
      <div class="relative w-full sm:w-72 sm:mr-auto">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <input 
          v-model="searchQuery"
          type="text" 
          class="block w-full min-h-[44px] pl-10 pr-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors"
          placeholder="Search exams..."
        >
      </div>

      <!-- Status -->
      <div class="flex flex-col gap-1.5 flex-1 sm:flex-none">
        <label class="text-[11px] font-bold text-slate-700 capitalize tracking-wide">Status</label>
        <div class="relative">
          <select v-model="selectedStatus" class="appearance-none min-h-[44px] border border-slate-200 rounded-xl text-sm pl-3 pr-10 py-2 text-slate-600 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors w-full sm:w-[140px]">
            <option>All Status</option>
            <option value="Upcoming">Upcoming</option>
            <option value="Active">Active</option>
            <option value="Completed">Completed</option>
            <option value="Drafts">Drafts</option>
          </select>
          <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
        </div>
      </div>

      <!-- Sort By -->
      <div class="flex flex-col gap-1.5 flex-1 sm:flex-none">
        <label class="text-[11px] font-bold text-slate-700 capitalize tracking-wide">Sort By</label>
        <div class="relative">
          <select v-model="sortBy" class="appearance-none min-h-[44px] border border-slate-200 rounded-xl text-sm pl-3 pr-10 py-2 text-slate-600 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors w-full sm:w-[140px]">
            <option>Exam Date</option>
            <option>Name (A-Z)</option>
          </select>
          <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
        </div>
      </div>

    </div>

    <!-- Desktop Table -->
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full text-left border-collapse whitespace-nowrap">
        <thead>
          <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wide">
            <th class="pb-4 pr-4 font-semibold">Exam Title</th>
            <th class="pb-4 px-4 font-semibold">Date & Time</th>
            <th class="pb-4 px-4 font-semibold text-center">Duration</th>
            <th class="pb-4 px-4 font-semibold text-center">Marks</th>
            <th class="pb-4 px-4 font-semibold text-center">Questions</th>
            <th class="pb-4 px-4 font-semibold text-center">Status</th>
            <th class="pb-4 pl-4 text-center font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody>
          <!-- Loading state -->
          <tr v-if="examStore.isLoading">
            <td colspan="5" class="py-8 text-center text-slate-500">
              <svg class="animate-spin -ml-1 mr-3 h-5 w-5 inline text-[#5138ed]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Loading exams...
            </td>
          </tr>
          
          <!-- Empty State -->
          <tr v-else-if="filteredExams.length === 0">
             <td colspan="5" class="py-8 text-center text-slate-500 font-medium">No exams found.</td>
          </tr>

          <!-- Data rows -->
          <tr v-else v-for="(exam, index) in filteredExams" :key="exam.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors last:border-0 group">
            <td class="py-4 pr-4">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" :class="[iconStyles[index % iconStyles.length].bg, iconStyles[index % iconStyles.length].text]">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="iconStyles[index % iconStyles.length].svg"></svg>
                </div>
                <div class="flex flex-col">
                  <span class="text-[13px] font-bold text-slate-700">{{ exam.title }}</span>
                  <span class="text-[11px] text-slate-400">{{ exam.course_name || 'Midterm Examination' }}</span>
                </div>
              </div>
            </td>

            <td class="py-4 px-4">
              <div class="flex flex-col">
                <span class="text-[13px] font-medium text-slate-600">{{ formatDate(exam.scheduled_at) }}</span>
                <span class="text-[11px] text-slate-400">{{ formatTime(exam.scheduled_at) }}</span>
              </div>
            </td>
            <td class="py-4 px-4 text-[13px] text-slate-600 font-medium text-center">
              <div class="flex items-center justify-center gap-1.5">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ exam.duration_minutes }} min
              </div>
            </td>
            <td class="py-4 px-4 text-[13px] text-slate-600 font-bold text-center">{{ exam.total_marks }}</td>
            <td class="py-4 px-4 text-[13px] text-slate-600 font-bold text-center">{{ exam.questions_count !== undefined ? exam.questions_count : '-' }}</td>

            <td class="py-4 px-4 text-center">
              <span class="px-2.5 py-1 text-[10px] font-bold rounded-md capitalize" :class="getStatusColor(exam.status)">
                {{ exam.status }}
              </span>
            </td>
            <td class="py-4 pl-4 text-center">
              <div class="flex items-center justify-center gap-1 text-slate-400">
                <button @click="openExamDetails(exam)" class="p-1.5 hover:text-[#5138ed] hover:bg-indigo-50 rounded-lg transition-colors" title="View Exam Details">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                </button>
                <template v-if="!lockStore.isLocked">
                  <router-link :to="`/instructor/exams/edit/${exam.id}`" class="p-1.5 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-colors" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                  </router-link>
                  <!-- ADD QUESTION ICON -->
                  <router-link :to="`/instructor/exams/edit/${exam.id}?step=2`" class="p-1.5 hover:text-emerald-500 hover:bg-emerald-50 rounded-lg transition-colors" title="Add Question">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                  </router-link>
                  <button @click="confirmDelete(exam.id)" :disabled="examStore.isSaving" class="p-1.5 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors disabled:opacity-50" title="Delete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                  </button>
                </template>
                <template v-else>
                  <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Locked</span>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile Card View -->
    <div class="md:hidden space-y-3">
      <div v-if="examStore.isLoading" class="py-10 text-center text-slate-500">
        <svg class="animate-spin h-6 w-6 text-[#5138ed] mx-auto mb-2" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Loading exams...
      </div>
      <div v-else-if="filteredExams.length === 0" class="py-10 text-center text-slate-500 font-medium">No exams found.</div>
      <div v-else v-for="(exam, index) in filteredExams" :key="exam.id" class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm">
        <!-- Card header -->
        <div class="flex items-start gap-3 mb-3">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5" :class="[iconStyles[index % iconStyles.length].bg, iconStyles[index % iconStyles.length].text]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="iconStyles[index % iconStyles.length].svg"></svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[13px] font-bold text-slate-800 leading-tight truncate">{{ exam.title }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">{{ exam.course_name || 'Midterm Examination' }}</p>
          </div>
          <span class="px-2.5 py-1 text-[10px] font-bold rounded-md capitalize shrink-0" :class="getStatusColor(exam.status)">{{ exam.status }}</span>
        </div>
        <!-- Card details -->
        <div class="grid grid-cols-3 gap-2 mb-3 text-center">
          <div class="bg-slate-50 rounded-xl p-2">
            <p class="text-[10px] text-slate-400 font-semibold mb-0.5">Date</p>
            <p class="text-[11px] font-bold text-slate-700">{{ formatDate(exam.scheduled_at) }}</p>
          </div>
          <div class="bg-slate-50 rounded-xl p-2">
            <p class="text-[10px] text-slate-400 font-semibold mb-0.5">Duration</p>
            <p class="text-[11px] font-bold text-slate-700">{{ exam.duration_minutes }}m</p>
          </div>
          <div class="bg-slate-50 rounded-xl p-2">
            <p class="text-[10px] text-slate-400 font-semibold mb-0.5">Marks</p>
            <p class="text-[11px] font-bold text-slate-700">{{ exam.total_marks }}</p>
          </div>
        </div>
        <!-- Card actions -->
        <div class="flex items-center gap-2 border-t border-slate-100 pt-3">
          <button @click="openExamDetails(exam)" class="flex-1 min-h-[36px] flex items-center justify-center gap-1.5 text-[12px] font-bold text-[#5138ed] bg-indigo-50 rounded-xl hover:bg-indigo-100 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
            View
          </button>
          <template v-if="!lockStore.isLocked">
            <router-link :to="`/instructor/exams/edit/${exam.id}`" class="flex-1 min-h-[36px] flex items-center justify-center gap-1.5 text-[12px] font-bold text-amber-600 bg-amber-50 rounded-xl hover:bg-amber-100 transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
              Edit
            </router-link>
            <button @click="confirmDelete(exam.id)" :disabled="examStore.isSaving" class="flex-1 min-h-[36px] flex items-center justify-center gap-1.5 text-[12px] font-bold text-rose-600 bg-rose-50 rounded-xl hover:bg-rose-100 transition-colors disabled:opacity-50">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
              Delete
            </button>
          </template>
          <template v-else>
            <span class="flex-1 text-center text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-2 rounded-xl border border-emerald-200">Locked</span>
          </template>
        </div>
      </div>
    </div>
    
    <!-- Pagination -->
    <div class="flex flex-wrap items-center justify-between gap-3 mt-6 pt-4 border-t border-slate-100">
      <span class="text-[12px] sm:text-[13px] text-slate-500 font-medium">
        Showing {{ filteredExams.length > 0 ? 1 : 0 }} to {{ filteredExams.length }} of {{ examStore.exams.length }} exams
      </span>
      <div class="flex items-center gap-2">
        <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <button class="w-9 h-9 flex items-center justify-center rounded-lg bg-[#5138ed] text-white font-bold text-sm shadow-sm shadow-indigo-200">1</button>
        <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold text-sm transition-colors">2</button>
        <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </button>
        
        <div class="relative ml-2">
          <select class="appearance-none border border-slate-200 rounded-xl text-sm pl-3 pr-8 py-1.5 text-slate-600 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors font-medium">
            <option>10 / page</option>
            <option>20 / page</option>
            <option>50 / page</option>
          </select>
          <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400">
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Exam Details Modal -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="showDetailsModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4" @keydown.escape="showDetailsModal = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]" @click.stop>
          
          <!-- Header -->
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
              </div>
              <div>
                <h3 class="text-[16px] font-bold text-slate-800">{{ selectedExam?.title || 'Exam Details' }}</h3>
                <p class="text-[12px] text-slate-500">{{ selectedExam?.course_code }} - {{ selectedExam?.course_name }}</p>
              </div>
            </div>
            <button @click="showDetailsModal = false" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 overflow-y-auto bg-slate-50 relative">
            <div v-if="isLoadingDetails" class="absolute inset-0 bg-slate-50/80 backdrop-blur-sm z-10 flex items-center justify-center">
              <div class="flex flex-col items-center gap-3">
                <svg class="w-8 h-8 text-[#5138ed] animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                <span class="text-sm font-bold text-slate-500">Loading exam details...</span>
              </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm mb-6">
              <h4 class="text-[14px] font-bold text-slate-800 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Exam Information
              </h4>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <div>
                  <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Status</p>
                  <span class="px-2.5 py-1 text-[11px] font-bold rounded-md capitalize inline-block" :class="getStatusColor(selectedExam?.status || '')">
                    {{ selectedExam?.status }}
                  </span>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Date & Time</p>
                  <p class="text-[13px] font-semibold text-slate-700">
                    {{ formatDate(selectedExam?.scheduled_at) }}<br/>
                    <span class="text-slate-400 font-normal">{{ formatTime(selectedExam?.scheduled_at) }}</span>
                  </p>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Duration</p>
                  <p class="text-[13px] font-semibold text-slate-700">{{ selectedExam?.duration_minutes }} Minutes</p>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Marks</p>
                  <p class="text-[13px] font-semibold text-slate-700">{{ selectedExam?.total_marks }} Points</p>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Questions</p>
                  <p class="text-[13px] font-semibold text-slate-700">{{ selectedExam?.questions_count !== undefined ? selectedExam?.questions_count : '-' }} Questions</p>
                </div>
                <div>
                  <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Assigned Students</p>
                  <p class="text-[13px] font-semibold text-slate-700">{{ selectedExam?.students_count !== undefined ? selectedExam?.students_count : '-' }} Students</p>
                </div>
              </div>
            </div>

            <!-- Settings Summary (if available) -->
            <div v-if="selectedExam?.settings" class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm mb-6">
              <h4 class="text-[14px] font-bold text-slate-800 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Exam Security & Settings
              </h4>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="px-4 py-3 bg-slate-50 rounded-xl flex justify-between items-center border border-slate-100">
                  <span class="text-[13px] font-medium text-slate-600">Shuffle Questions</span>
                  <span class="text-[12px] font-bold" :class="selectedExam.settings.shuffle_questions ? 'text-emerald-600' : 'text-slate-400'">{{ selectedExam.settings.shuffle_questions ? 'Enabled' : 'Disabled' }}</span>
                </div>
                <div class="px-4 py-3 bg-slate-50 rounded-xl flex justify-between items-center border border-slate-100">
                  <span class="text-[13px] font-medium text-slate-600">Allow Calculator</span>
                  <span class="text-[12px] font-bold" :class="selectedExam.settings.allow_calculator ? 'text-emerald-600' : 'text-slate-400'">{{ selectedExam.settings.allow_calculator ? 'Enabled' : 'Disabled' }}</span>
                </div>
                <div class="px-4 py-3 bg-slate-50 rounded-xl flex justify-between items-center border border-slate-100">
                  <span class="text-[13px] font-medium text-slate-600">Browser Tab Monitoring</span>
                  <span class="text-[12px] font-bold" :class="selectedExam.settings.enable_browser_tab_monitoring ? 'text-emerald-600' : 'text-slate-400'">{{ selectedExam.settings.enable_browser_tab_monitoring ? 'Enabled' : 'Disabled' }}</span>
                </div>
                <div class="px-4 py-3 bg-slate-50 rounded-xl flex justify-between items-center border border-slate-100">
                  <span class="text-[13px] font-medium text-slate-600">Show Results After</span>
                  <span class="text-[12px] font-bold" :class="selectedExam.settings.show_results_after ? 'text-emerald-600' : 'text-slate-400'">{{ selectedExam.settings.show_results_after ? 'Enabled' : 'Disabled' }}</span>
                </div>
              </div>
            </div>

            <!-- Questions List Grouped by Type -->
            <div v-if="selectedExam?.questions && selectedExam.questions.length > 0" class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
              <h4 class="text-[14px] font-bold text-slate-800 mb-6 flex items-center gap-2 border-b border-slate-100 pb-4">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                Questions ({{ selectedExam.questions.length }})
              </h4>
              
              <div class="space-y-12">
                <!-- Iterate over Question Types -->
                <div v-for="(typeGroup, typeIdx) in groupedQuestions" :key="typeGroup.questionType">
                  <!-- TYPE HEADER -->
                  <div class="mb-5 border-b-2 border-slate-100 pb-3">
                    <h2 class="text-[14px] font-black text-slate-800 uppercase tracking-widest flex items-center gap-3">
                      <span class="w-1.5 h-5 bg-[#5138ed] rounded-full inline-block shadow-[0_0_10px_rgba(81,56,237,0.4)]"></span>
                      {{ typeGroup.questionType }}
                    </h2>
                  </div>

                  <!-- Iterate over Instructions within Type -->
                  <div class="space-y-8 pl-0 sm:pl-5 border-l-[3px] border-slate-50 ml-1">
                    <div v-for="(instGroup, instIdx) in typeGroup.instructionGroups" :key="instIdx">
                      
                      <!-- INSTRUCTION HEADER -->
                      <div class="mb-4 relative">
                        <div class="absolute -left-[28px] top-1/2 -translate-y-1/2 w-3 h-3 bg-white border-[3px] border-indigo-400 rounded-full"></div>
                        <div v-if="instGroup.instruction">
                          <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block mb-1">Instructor Instruction:</span>
                          <h3 class="text-[13px] font-bold text-slate-700 italic px-2">"{{ instGroup.instruction }}"</h3>
                        </div>
                        <div v-else>
                          <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block mb-1">Instructor Instruction:</span>
                          <h3 class="text-[12px] font-bold text-slate-400 italic px-2">No specific instruction provided.</h3>
                        </div>
                      </div>

                      <!-- Questions for this Instruction -->
                      <div class="flex flex-col gap-3 pl-3">
                        <div v-for="(q, qIdx) in instGroup.questions" :key="q.id || qIdx" class="p-4 bg-slate-50 border border-slate-100 rounded-xl relative">
                          <div class="flex justify-between items-start gap-4 mb-3">
                            <div class="flex items-center gap-2">
                              <span class="w-6 h-6 rounded-md bg-white border border-slate-200 flex items-center justify-center text-[11px] font-bold text-indigo-600 shadow-sm">{{ getQuestionIndexWithinType(q) }}</span>
                              <span class="px-2 py-0.5 bg-indigo-50 text-[#5138ed] text-[10px] font-bold uppercase rounded">{{ q.type.replace(/_/g, ' ') }}</span>
                            </div>
                            <span class="text-[11px] font-bold text-slate-500 bg-white px-2 py-1 rounded-lg border border-slate-200">{{ q.marks || 1 }} Marks</span>
                          </div>
                          
                          <p class="text-[13px] text-slate-700 font-medium leading-relaxed mb-3" v-html="q.text"></p>
                          
                          <div v-if="q.options && q.options.length > 0" class="flex flex-col gap-2 mb-4 ml-2">
                            <div v-for="(opt, oIdx) in q.options" :key="oIdx" class="flex items-start gap-2">
                              <span class="text-[11px] font-bold text-slate-400 mt-0.5">{{ String.fromCharCode(65 + Number(oIdx)) }}.</span>
                              <p class="text-[12px] text-slate-600" v-html="typeof opt === 'string' ? opt : (opt.text || opt)"></p>
                            </div>
                          </div>

                          <!-- Display Answer Based on Type -->
                          <div class="pl-3 border-l-2 border-emerald-400">
                            <p class="text-[10px] font-bold text-emerald-600 mb-1 uppercase tracking-wider">Correct Answer</p>
                            <p class="text-[12px] text-slate-700 font-medium bg-white px-3 py-1.5 rounded-lg border border-emerald-100 inline-block" v-html="q.correct_answer || 'N/A'"></p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else-if="!isLoadingDetails" class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm text-center">
              <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
              </div>
              <p class="text-[13px] font-bold text-slate-500">No questions found for this exam.</p>
            </div>
          </div>
          
          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
            <button @click="showDetailsModal = false" class="px-5 py-2.5 border border-slate-200 text-slate-600 font-bold text-[13px] rounded-xl hover:bg-white transition-colors shadow-sm">
              Close
            </button>
            <router-link :to="`/instructor/exams/edit/${selectedExam?.id}`" class="px-5 py-2.5 bg-[#5138ed] text-white font-bold text-[13px] rounded-xl hover:bg-indigo-600 transition-colors shadow-sm flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
              Edit Exam
            </router-link>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Delete Confirmation Modal -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="showDeleteModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4" @keydown.escape="showDeleteModal = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden" @click.stop>
          <div class="p-6 text-center">
            <div class="w-16 h-16 bg-rose-50 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </div>
            <h3 class="text-[18px] font-bold text-slate-800 mb-2">Delete Exam</h3>
            <p class="text-[13px] text-slate-500 mb-6">Are you sure you want to delete this exam? This action cannot be undone.</p>
            
            <div class="flex gap-3">
              <button @click="showDeleteModal = false" class="flex-1 px-5 py-2.5 border border-slate-200 text-slate-600 font-bold text-[13px] rounded-xl hover:bg-slate-50 transition-colors">
                Cancel
              </button>
              <button @click="executeDelete" :disabled="isDeleting" class="flex-1 px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-[13px] rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2 disabled:opacity-50">
                <span v-if="isDeleting">Deleting...</span>
                <span v-else>Confirm Delete</span>
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
