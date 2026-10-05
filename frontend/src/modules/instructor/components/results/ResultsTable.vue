<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useInstructorResultStore } from '../../store/instructorResultStore'

const router = useRouter()

const resultStore = useInstructorResultStore()

// Filter states
const searchQuery = ref('')
const selectedType = ref('All Types')
const selectedStatus = ref('All Status')
const sortBy = ref('Exam Date')

// Helper for row icons
const getIconClass = (index: number) => {
  const classes = [
    'text-indigo-500 bg-indigo-50 border-indigo-100',
    'text-emerald-500 bg-emerald-50 border-emerald-100',
    'text-orange-500 bg-orange-50 border-orange-100',
    'text-rose-500 bg-rose-50 border-rose-100'
  ]
  return classes[index % classes.length]
}

// Helpers for color logic
const getStatusBadge = (status: string) => {
  if (status === 'Published')   return 'bg-emerald-50 text-emerald-600'
  if (status === 'Graded')      return 'bg-blue-50 text-blue-600'
  if (status === 'Completed')   return 'bg-blue-50 text-blue-600'
  if (status === 'Pending')     return 'bg-amber-50 text-amber-600'
  if (status === 'Draft')       return 'bg-slate-100 text-slate-500'
  return 'bg-slate-50 text-slate-400'
}

const getTypeBadge = (type: string) => {
  if (type === 'Mid Exam') return 'text-indigo-600 font-semibold bg-indigo-50 px-2 py-0.5 rounded'
  if (type === 'Quiz') return 'text-emerald-500 font-semibold bg-emerald-50 px-2 py-0.5 rounded'
  if (type === 'Assignment') return 'text-rose-500 font-semibold bg-rose-50 px-2 py-0.5 rounded'
  if (type === 'Practice') return 'text-blue-500 font-semibold bg-blue-50 px-2 py-0.5 rounded'
  if (type === 'Final Exam') return 'text-blue-600 font-semibold bg-blue-50 px-2 py-0.5 rounded'
  if (type === 'Practical') return 'text-orange-500 font-semibold bg-orange-50 px-2 py-0.5 rounded'
  return 'text-slate-600 font-semibold bg-slate-50 px-2 py-0.5 rounded'
}
</script>

<template>
  <div class="bg-white border border-slate-100 rounded-2xl shadow-sm mb-6 flex flex-col">
    <!-- Filter Bar -->
    <div class="p-4 border-b border-slate-100 flex flex-wrap items-center gap-3">
      
      <!-- Search -->
      <div class="relative min-w-[200px] flex-1">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
        <input type="text" v-model="searchQuery" class="block w-full pl-9 pr-3 py-2 min-h-[44px] border border-slate-200 rounded-xl leading-5 bg-slate-50 placeholder-slate-400 focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] text-sm transition-colors" placeholder="Search exams...">
      </div>
      
      <!-- Exam Type -->
      <div class="relative flex-1 sm:flex-none">
        <select v-model="selectedType" class="appearance-none border border-slate-200 rounded-xl text-sm pl-3 pr-8 py-2 min-h-[44px] text-slate-600 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] bg-white transition-colors w-full sm:w-[130px]">
          <option>All Types</option>
          <option>Mid Exam</option>
          <option>Quiz</option>
          <option>Final Exam</option>
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>
      </div>
      
      <!-- Status -->
      <div class="relative flex-1 sm:flex-none">
        <select v-model="selectedStatus" class="appearance-none border border-slate-200 rounded-xl text-sm pl-3 pr-8 py-2 min-h-[44px] text-slate-600 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] bg-white transition-colors w-full sm:w-[130px]">
          <option>All Status</option>
          <option>Draft</option>
          <option>Pending</option>
          <option>Completed</option>
          <option>Published</option>
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>
      </div>
      
      <!-- Sort By -->
      <div class="relative flex-1 sm:flex-none">
        <select v-model="sortBy" class="appearance-none border border-slate-200 rounded-xl text-sm pl-3 pr-8 py-2 min-h-[44px] text-slate-600 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] bg-white transition-colors w-full sm:w-[130px]">
          <option>Exam Date</option>
          <option>Name (A-Z)</option>
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>
      </div>
      
      <!-- Date Range -->
      <div class="relative flex items-center px-3 py-2 min-h-[44px] border border-slate-200 rounded-xl bg-white text-sm text-slate-600 w-full sm:w-auto shrink-0 whitespace-nowrap">
        <svg class="w-4 h-4 mr-2 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        <span>May 01, 2025 - May 25, 2025</span>
      </div>

    </div>

    <!-- Desktop Table -->
    <div class="hidden md:block overflow-x-auto w-full">
      <table class="w-full text-left border-collapse whitespace-nowrap">
        <thead>
          <tr class="text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
            <th class="pb-4 pt-4 pl-4 font-semibold">Exam Title</th>
            <th class="pb-4 pt-4 px-4 font-semibold text-center">Exam Type</th>
            <th class="pb-4 pt-4 px-4 font-semibold">Exam Date</th>
            <th class="pb-4 pt-4 px-4 font-semibold text-center">Students</th>
            <th class="pb-4 pt-4 px-4 font-semibold text-center">Submitted</th>
            <th class="pb-4 pt-4 px-4 font-semibold text-center">Published</th>
            <th class="pb-4 pt-4 px-4 font-semibold text-center">Status</th>
            <th class="pb-4 pt-4 pl-4 pr-4 text-center font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody>
          <!-- Loading State -->
          <tr v-if="resultStore.isLoading">
            <td colspan="9" class="py-12 text-center">
              <div class="flex flex-col items-center justify-center">
                <div class="w-8 h-8 border-4 border-indigo-200 border-t-[#5138ed] rounded-full animate-spin mb-3"></div>
                <p class="text-sm font-medium text-slate-500">Loading results...</p>
              </div>
            </td>
          </tr>
          
          <!-- Empty State -->
          <tr v-else-if="resultStore.results.length === 0">
            <td colspan="9" class="py-12 text-center">
              <div class="flex flex-col items-center justify-center text-slate-400">
                <svg class="w-12 h-12 mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <p class="text-sm font-medium text-slate-500">No exams found.</p>
              </div>
            </td>
          </tr>

          <!-- Data rows -->
          <tr v-else v-for="(exam, index) in resultStore.results" :key="exam.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors last:border-0 group">
            <td class="py-4 pl-4 pr-4">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 border" :class="getIconClass(index)">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <div class="flex flex-col">
                  <span class="text-[12px] font-bold text-slate-800">{{ exam.title }}</span>
                  <span class="text-[11px] text-slate-400">{{ exam.subtitle }}</span>
                </div>
              </div>
            </td>
            
            <td class="py-4 px-4 text-center">
              <span class="text-[11px]" :class="getTypeBadge(exam.type)">{{ exam.type }}</span>
            </td>
            
            <td class="py-4 px-4">
              <div class="flex flex-col">
                <span class="text-[12px] font-bold text-slate-700">{{ new Date(exam.scheduled_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) }}</span>
                <span class="text-[11px] text-slate-400">{{ new Date(exam.scheduled_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) }}</span>
              </div>
            </td>
            
            <td class="py-4 px-4 text-[12px] font-bold text-slate-700 text-center">
              {{ exam.total_students }}
            </td>
            
            <td class="py-4 px-4 text-center">
              <div class="flex flex-col items-center">
                <span class="text-[12px] font-bold" :class="exam.submitted_count === exam.total_students ? 'text-emerald-500' : (exam.submitted_count === 0 ? 'text-rose-500' : 'text-emerald-500')">
                  {{ exam.submitted_count }}
                </span>
                <span class="text-[10px]" :class="exam.submitted_count === exam.total_students ? 'text-emerald-400' : (exam.submitted_count === 0 ? 'text-rose-400' : 'text-emerald-400')">
                  ({{ exam.total_students > 0 ? ((exam.submitted_count / exam.total_students) * 100).toFixed(1).replace('.0', '') : 0 }}%)
                </span>
              </div>
            </td>
            <td class="py-4 px-4 text-center">
              <svg v-if="exam.is_published" class="w-4 h-4 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
              <svg v-else class="w-4 h-4 text-rose-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
            </td>
            
            <td class="py-4 px-4 text-center">
              <span class="px-2.5 py-1 text-[10px] font-bold rounded-md capitalize" :class="getStatusBadge(exam.status)">
                {{ exam.status }}
              </span>
            </td>
            
            <td class="py-4 px-4 text-center">
              <div class="flex items-center justify-center gap-1 text-slate-400">
                <button @click="router.push({ name: 'ExamResultDetail', params: { examId: exam.id } })" class="p-1.5 hover:text-[#5138ed] hover:bg-indigo-50 rounded-lg transition-colors" title="View Details">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                </button>
                <button class="p-1.5 hover:text-blue-500 hover:bg-blue-50 rounded-lg transition-colors" title="Analytics">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile Card View -->
    <div class="md:hidden divide-y divide-slate-100">
      <div v-if="resultStore.isLoading" class="p-8 text-center">
        <div class="w-8 h-8 border-4 border-indigo-200 border-t-[#5138ed] rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-sm font-medium text-slate-500">Loading results...</p>
      </div>
      <div v-else-if="resultStore.results.length === 0" class="p-8 text-center text-slate-400">
        <svg class="w-12 h-12 mb-3 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        <p class="text-sm font-medium text-slate-500">No exams found.</p>
      </div>
      <div v-else v-for="(exam, index) in resultStore.results" :key="exam.id" class="p-4">
        <div class="flex items-start gap-3 mb-3">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 border mt-0.5" :class="getIconClass(index)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[13px] font-bold text-slate-800 truncate">{{ exam.title }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">{{ exam.subtitle }}</p>
          </div>
          <span class="px-2.5 py-1 text-[10px] font-bold rounded-md capitalize shrink-0" :class="getStatusBadge(exam.status)">{{ exam.status }}</span>
        </div>
        <div class="grid grid-cols-3 gap-2 mb-3 text-center">
          <div class="bg-slate-50 rounded-xl p-2">
            <p class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">Date</p>
            <p class="text-[11px] font-bold text-slate-700">{{ new Date(exam.scheduled_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit' }) }}</p>
          </div>
          <div class="bg-slate-50 rounded-xl p-2">
            <p class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">Students</p>
            <p class="text-[11px] font-bold text-slate-700">{{ exam.total_students }}</p>
          </div>
          <div class="bg-slate-50 rounded-xl p-2">
            <p class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">Submitted</p>
            <p class="text-[11px] font-bold text-emerald-600">{{ exam.submitted_count }}</p>
          </div>
        </div>
        <div class="flex items-center justify-between pt-2 border-t border-slate-50">
          <span class="text-[11px]" :class="getTypeBadge(exam.type)">{{ exam.type }}</span>
          <div class="flex items-center gap-1.5">
            <button @click="router.push({ name: 'ExamResultDetail', params: { examId: exam.id } })" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 transition-colors" title="View Details">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
            </button>
            <button class="w-8 h-8 rounded-lg flex items-center justify-center text-blue-500 bg-blue-50 hover:bg-blue-100 transition-colors" title="Analytics">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Pagination -->
    <div class="p-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 mt-auto">
      <span class="text-[12px] text-slate-500 font-medium">
        {{ resultStore.results.length > 0 ? `Showing 1 to ${resultStore.results.length} of ${resultStore.results.length} exams` : 'No exams to display' }}
      </span>
      <div class="flex items-center gap-1.5">
        <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors disabled:opacity-50" disabled>
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <button class="w-8 h-8 flex items-center justify-center rounded-lg bg-[#5138ed] text-white font-bold text-[12px] shadow-sm shadow-indigo-200">1</button>
        <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors disabled:opacity-50" disabled>
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </button>
      </div>
    </div>
  </div>
</template>
