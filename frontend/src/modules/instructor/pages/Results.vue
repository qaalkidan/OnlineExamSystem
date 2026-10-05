<script setup lang="ts">
import { onMounted } from 'vue'
import { useInstructorResultStore } from '../store/instructorResultStore'

import ResultsStats from '../components/results/ResultsStats.vue'
import ResultsTable from '../components/results/ResultsTable.vue'
import OverallPerformanceWidget from '../components/results/OverallPerformanceWidget.vue'
import PerformanceSummaryWidget from '../components/results/PerformanceSummaryWidget.vue'
import ResultsQuickActionsWidget from '../components/results/ResultsQuickActionsWidget.vue'

const resultStore = useInstructorResultStore()

onMounted(() => {
  resultStore.fetchResults()
})
</script>

<template>
  <div class="max-w-[1500px] mx-auto">
    
    <!-- Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-800">Results Dashboard</h1>
        <p class="text-[14px] text-slate-500 mt-1">View and manage results for your course examinations.</p>
      </div>
    </div>

    <!-- Course Information Card -->
    <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-6 shadow-sm mb-6 flex flex-col sm:flex-row flex-wrap items-start sm:items-center justify-between gap-4 sm:gap-6">
      <!-- Left: Icon & Details -->
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-2xl bg-indigo-50 flex items-center justify-center text-[#5138ed] shrink-0">
          <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
        </div>
        <div>
          <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Course Information</div>
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <h2 class="text-lg sm:text-xl font-extrabold text-slate-800">{{ resultStore.course.name }}</h2>
            <span class="px-2 py-0.5 text-[10px] font-bold bg-emerald-50 text-emerald-600 rounded-md">{{ resultStore.course.status }}</span>
          </div>
          <div class="flex flex-wrap items-center gap-3 text-[12px] sm:text-[13px] text-slate-500 font-medium">
            <span>Code: {{ resultStore.course.code }}</span>
            <span>Instructor: {{ resultStore.course.instructor_name }}</span>
          </div>
        </div>
      </div>

      <!-- Right: Attributes -->
      <div class="flex flex-wrap items-center gap-4 sm:gap-8">
        <!-- Semester -->
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-[#5138ed]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
          </div>
          <div class="flex flex-col">
            <span class="text-[10px] font-bold text-slate-400">Semester</span>
            <span class="text-[13px] font-bold text-slate-700">{{ resultStore.course.semester }}</span>
          </div>
        </div>
        <!-- Academic Year -->
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-[#5138ed]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
          </div>
          <div class="flex flex-col">
            <span class="text-[10px] font-bold text-slate-400">Academic Year</span>
            <span class="text-[13px] font-bold text-slate-700">{{ resultStore.course.academic_year }}</span>
          </div>
        </div>
        <!-- Department -->
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-[#5138ed]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
          </div>
          <div class="flex flex-col">
            <span class="text-[10px] font-bold text-slate-400">Department</span>
            <span class="text-[13px] font-bold text-slate-700">{{ resultStore.course.department }}</span>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Main Content Area -->
    <div class="space-y-6">
      
      <!-- Top Section: Stats & Full-Width Results Table -->
      <div class="w-full">
        <ResultsStats />
        <ResultsTable />
      </div>

      <!-- Bottom Section: 3-Column Grid (Grading Progress, Pending Manual Grading, Quick Actions) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <OverallPerformanceWidget class="h-full" />
        <PerformanceSummaryWidget class="h-full" />
        <ResultsQuickActionsWidget class="h-full" />
      </div>

    </div>
  </div>
</template>
