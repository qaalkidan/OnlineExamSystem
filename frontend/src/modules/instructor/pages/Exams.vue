<script setup lang="ts">
import { onMounted } from 'vue'
import { useInstructorExamStore } from '../store/instructorExamStore'
import { useSemesterLockStore } from '../store/semesterLockStore'

import ExamStatCards from '../components/exam/ExamStatCards.vue'

import ExamTable from '../components/exam/ExamTable.vue'
import ExamCalendar from '../components/exam/ExamCalendar.vue'
import ExamOverviewChart from '../components/exam/ExamOverviewChart.vue'
import ExamQuickActions from '../components/exam/ExamQuickActions.vue'

const examStore = useInstructorExamStore()
const lockStore = useSemesterLockStore()

onMounted(() => {
  examStore.fetchExams()
  lockStore.fetchLockStatus()
})
</script>

<template>
  <div class="max-w-[1400px] mx-auto flex flex-col gap-6">
    
    <!-- Main Left Column -->
    <div class="flex-1 min-w-0 space-y-6">
      
      <!-- Page Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-2">
        <div>
          <h1 class="text-2xl font-bold text-slate-800">All Exams</h1>
          <p class="text-[14px] text-slate-500 mt-1">View and manage all your exams across all statuses.</p>
        </div>
        <!-- Top Action Buttons -->
        <div class="flex flex-col md:flex-row items-center gap-3 w-full md:w-auto mt-4 md:mt-0">

          <router-link
            v-if="!lockStore.isLocked"
            to="/instructor/exams/create"
            class="flex items-center justify-center gap-2 bg-[#5138ed] hover:bg-indigo-600 text-white px-5 py-2 rounded-xl font-bold text-[13px] shadow-sm transition-colors whitespace-nowrap"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Create New Exam
          </router-link>
          <div
            v-else
            @click="lockStore.promptLockedNotice('create exam')"
            class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 rounded-xl font-bold text-xs shadow-xs cursor-pointer hover:bg-emerald-100 transition-colors whitespace-nowrap"
          >
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <span>Semester Locked (Read-Only)</span>
          </div>
          <button class="flex items-center justify-center bg-white border border-slate-200 text-slate-700 hover:text-[#5138ed] hover:border-indigo-200 px-4 py-2 rounded-xl font-bold text-[13px] shadow-sm transition-colors gap-2">
            <svg class="w-4 h-4 text-slate-400 group-hover:text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
            Export
          </button>
        </div>
      </div>

      <!-- Dev Banner: Mock Data Active -->
      <div
        v-if="examStore.usingMockData"
        class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-4 py-3 text-sm font-medium flex items-center gap-2"
      >
        <svg class="w-4 h-4 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>
          <strong>Dev Mode:</strong> Backend is offline — showing mock data.
        </span>
      </div>

      <!-- Error Banner: Real API Error -->
      <div v-if="examStore.error" class="bg-rose-50 border border-rose-200 text-rose-700 rounded-xl px-4 py-3 text-sm font-medium flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        {{ examStore.error }}
      </div>

      <!-- Stat Cards -->
      <ExamStatCards />

      <!-- Table Wrapper -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden flex flex-col">
        <!-- Main Table Area -->
        <div class="p-0">
          <ExamTable class="border-0 shadow-none rounded-none" />
        </div>
      </div>

    </div>

      <!-- Bottom Section -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
        <ExamCalendar />
        <ExamOverviewChart />
        <ExamQuickActions />
      </div>

  </div>
</template>
