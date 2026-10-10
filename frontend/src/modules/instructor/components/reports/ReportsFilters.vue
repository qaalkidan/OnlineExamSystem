<script setup lang="ts">
import { ref } from 'vue'
import { useInstructorReportStore } from '../../store/instructorReportStore'

const reportStore = useInstructorReportStore()

const emit = defineEmits<{
  (e: 'filterChange', filters: { dateRange: string; exam: string; course: string; examType: string }): void
  (e: 'refresh'): void
}>()

const selectedDateRange = ref('May 01, 2025 - May 25, 2025')
const selectedExam = ref('All Exams')
const selectedCourse = ref('All Courses')
const selectedExamType = ref('All Exam Types')
const showDateDropdown = ref(false)
const isFiltering = ref(false)

const datePresets = [
  'May 01, 2025 - May 25, 2025',
  'Last 7 Days',
  'Last 30 Days',
  'Current Semester (2028-S2)',
  'Full Academic Year 2027/28'
]

const exams = [
  'All Exams',
  'Database Systems Mid Exam',
  'Web Programming Quiz 1',
  'Data Structures Final Exam',
  'Operating Systems Mid Exam',
  'Theory of Computation Final'
]

const courses = [
  'All Courses',
  'CS 301 - Database Systems',
  'CS 204 - Web Programming',
  'CS 202 - Data Structures',
  'CS 305 - Operating Systems'
]

const examTypes = [
  'All Exam Types',
  'Mid Exam',
  'Final Exam',
  'Quiz',
  'Assignment'
]

const selectDateRange = (range: string) => {
  selectedDateRange.value = range
  showDateDropdown.value = false
  triggerFilter()
}

const triggerFilter = () => {
  isFiltering.value = true
  emit('filterChange', {
    dateRange: selectedDateRange.value,
    exam: selectedExam.value,
    course: selectedCourse.value,
    examType: selectedExamType.value
  })
  setTimeout(() => {
    isFiltering.value = false
  }, 300)
}

const handleRefresh = async () => {
  emit('refresh')
  await reportStore.fetchReports()
}
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-4 mb-2">
    
    <!-- Left Filter Controls -->
    <div class="flex flex-wrap items-center gap-3">
      
      <!-- Date Range Selector -->
      <div class="relative min-w-[210px]">
        <button
          type="button"
          @click="showDateDropdown = !showDateDropdown"
          class="w-full px-3.5 py-2 text-[11px] border border-[#E6EBF3] rounded-xl text-[#17243A] bg-white hover:border-[#4F35F3]/50 focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] cursor-pointer font-medium flex items-center justify-between shadow-2xs transition-all text-left"
        >
          <span class="truncate">{{ selectedDateRange }}</span>
          <svg class="w-3.5 h-3.5 text-slate-400 shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </button>

        <!-- Date Range Presets Dropdown -->
        <div
          v-if="showDateDropdown"
          @click="showDateDropdown = false"
          class="absolute left-0 top-full mt-1.5 w-60 bg-white border border-[#E6EBF3] rounded-xl shadow-lg py-1.5 z-40 animate-in fade-in duration-100"
        >
          <div class="px-3 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Date Presets</div>
          <button
            v-for="preset in datePresets"
            :key="preset"
            @click="selectDateRange(preset)"
            class="w-full text-left px-3.5 py-1.5 text-xs text-slate-700 hover:bg-[#EEF0FF] hover:text-[#4F35F3] flex items-center justify-between transition-colors cursor-pointer"
            :class="{ 'font-bold text-[#4F35F3] bg-indigo-50/50': selectedDateRange === preset }"
          >
            <span>{{ preset }}</span>
            <span v-if="selectedDateRange === preset" class="w-1.5 h-1.5 rounded-full bg-[#4F35F3]"></span>
          </button>
        </div>
      </div>

      <!-- Exams Dropdown -->
      <div class="relative min-w-[130px]">
        <select
          v-model="selectedExam"
          @change="triggerFilter"
          class="w-full appearance-none pl-3 pr-8 py-2 text-[11px] border border-[#E6EBF3] rounded-xl focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-[#17243A] bg-white hover:border-slate-300 cursor-pointer font-medium shadow-2xs transition-all"
        >
          <option v-for="item in exams" :key="item" :value="item">{{ item }}</option>
        </select>
        <svg class="w-3.5 h-3.5 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </div>

      <!-- Courses Dropdown -->
      <div class="relative min-w-[130px]">
        <select
          v-model="selectedCourse"
          @change="triggerFilter"
          class="w-full appearance-none pl-3 pr-8 py-2 text-[11px] border border-[#E6EBF3] rounded-xl focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-[#17243A] bg-white hover:border-slate-300 cursor-pointer font-medium shadow-2xs transition-all"
        >
          <option v-for="item in courses" :key="item" :value="item">{{ item }}</option>
        </select>
        <svg class="w-3.5 h-3.5 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </div>

      <!-- Exam Types Dropdown -->
      <div class="relative min-w-[140px]">
        <select
          v-model="selectedExamType"
          @change="triggerFilter"
          class="w-full appearance-none pl-3 pr-8 py-2 text-[11px] border border-[#E6EBF3] rounded-xl focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-[#17243A] bg-white hover:border-slate-300 cursor-pointer font-medium shadow-2xs transition-all"
        >
          <option v-for="item in examTypes" :key="item" :value="item">{{ item }}</option>
        </select>
        <svg class="w-3.5 h-3.5 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </div>

      <!-- Filter Button -->
      <button
        @click="triggerFilter"
        :disabled="isFiltering"
        class="px-4 py-2 border border-[#4F35F3] text-[#4F35F3] bg-white hover:bg-[#EEF0FF] active:bg-indigo-100 font-bold text-[11px] rounded-xl transition-all flex items-center gap-1.5 shadow-2xs cursor-pointer"
        title="Apply Reports Filter"
      >
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
        </svg>
        <span>Filter</span>
      </button>
    </div>

    <!-- Data Refresh Text & Sync Button -->
    <div
      @click="handleRefresh"
      class="flex items-center gap-2 text-slate-400 hover:text-[#4F35F3] cursor-pointer transition-colors group select-none py-1"
      title="Click to refresh academic reports data"
    >
      <span class="text-[10px] font-medium text-slate-500 group-hover:text-[#4F35F3]">
        Data as of May 25, 2025 10:30 AM
      </span>
      <svg
        class="w-3.5 h-3.5 text-slate-400 group-hover:text-[#4F35F3] transition-transform"
        :class="{ 'animate-spin text-[#4F35F3]': reportStore.isLoading }"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
      </svg>
    </div>

  </div>
</template>
