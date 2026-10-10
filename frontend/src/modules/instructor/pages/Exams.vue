<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useInstructorExamStore } from '../store/instructorExamStore'
import { useSemesterLockStore } from '../store/semesterLockStore'

import ExamStatCards from '../components/exam/ExamStatCards.vue'
import ExamTable from '../components/exam/ExamTable.vue'
import ExamCalendar from '../components/exam/ExamCalendar.vue'
import ExamOverviewChart from '../components/exam/ExamOverviewChart.vue'
import ExamQuickActions from '../components/exam/ExamQuickActions.vue'

const examStore = useInstructorExamStore()
const lockStore = useSemesterLockStore()
const tableRef = ref<{ exportToCsv: () => void } | null>(null)

onMounted(() => {
  examStore.fetchExams()
  lockStore.fetchLockStatus()
})

const handleExport = () => {
  if (tableRef.value?.exportToCsv) {
    tableRef.value.exportToCsv()
  }
}

const handleRetry = () => {
  examStore.fetchExams()
}
</script>

<template>
  <div class="max-w-[1400px] mx-auto space-y-6">
    
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-1">
      <div>
        <h1 class="text-2xl sm:text-[26px] font-black text-slate-900 tracking-tight leading-tight">
          Exams
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1 font-medium">
          Manage, review, and monitor your examinations in one place.
        </p>
      </div>

      <!-- Action & Lock Controls -->
      <div class="flex items-center gap-3">
        <!-- Unlocked: Create New Exam -->
        <router-link
          v-if="!lockStore.isLocked"
          to="/instructor/exams/create"
          class="flex items-center justify-center gap-2 bg-[#5138ed] hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold text-xs sm:text-sm shadow-xs hover:shadow transition-all w-full sm:w-auto min-h-[44px]"
        >
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          <span>Create New Exam</span>
        </router-link>

        <!-- Locked: Semester Locked Pill -->
        <div
          v-else
          @click="lockStore.promptLockedNotice('create exam')"
          class="flex items-center justify-center gap-2 bg-emerald-50 hover:bg-emerald-100/80 border border-emerald-200/80 text-emerald-800 px-4 py-2.5 rounded-xl font-bold text-xs shadow-2xs cursor-pointer transition-colors w-full sm:w-auto min-h-[44px]"
          title="Examinations locked after academic submission. Click for details."
        >
          <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          <span>Semester Locked (Read-Only)</span>
        </div>

        <!-- Export CSV Button -->
        <button 
          @click="handleExport"
          class="flex items-center justify-center gap-2 bg-white border border-slate-200 text-slate-700 hover:text-[#5138ed] hover:border-indigo-200 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm shadow-2xs transition-colors shrink-0 min-h-[44px] cursor-pointer"
          title="Export current exams list to CSV"
        >
          <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
          <span>Export</span>
        </button>
      </div>
    </div>

    <!-- Polished Semester Lock Banner (When Locked) -->
    <div
      v-if="lockStore.isLocked"
      class="bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50 border border-emerald-200/90 rounded-2xl p-4 sm:p-5 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4"
    >
      <div class="flex items-start gap-3.5 min-w-0">
        <div class="w-10 h-10 rounded-xl bg-emerald-100/90 border border-emerald-200 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <div>
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <h3 class="text-sm font-bold text-emerald-950">
              Semester Locked — Read-Only Access
            </h3>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-200/60 text-emerald-900 border border-emerald-300/60 uppercase tracking-wider">
              {{ lockStore.statusLabel }}
            </span>
          </div>
          <p class="text-xs text-emerald-800/90 leading-relaxed max-w-3xl">
            Your semester submission is complete. Examinations and questions are available for review, but modifications, scheduling updates, and exam deletions are disabled.
          </p>
          <div class="flex flex-wrap items-center gap-3 text-[11px] text-emerald-700/80 font-medium mt-2 pt-2 border-t border-emerald-200/60">
            <span>Academic Year: <strong class="text-emerald-900">{{ lockStore.academicYear }}</strong></span>
            <span>•</span>
            <span>Semester: <strong class="text-emerald-900">{{ lockStore.semester }}</strong></span>
            <span v-if="lockStore.submittedAt">•</span>
            <span v-if="lockStore.submittedAt">Submitted: <strong class="text-emerald-900">{{ lockStore.submittedAt }}</strong></span>
          </div>
        </div>
      </div>

      <router-link
        to="/instructor/semester-submission"
        class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-2xs transition-colors shrink-0 min-h-[40px]"
      >
        <span>View Submission Details</span>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </router-link>
    </div>

    <!-- Dev Mode Banner (If offline) -->
    <div
      v-if="examStore.usingMockData"
      class="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl px-4 py-3 text-xs sm:text-sm font-medium flex items-center justify-between gap-3 shadow-2xs"
    >
      <div class="flex items-center gap-2.5">
        <svg class="w-4 h-4 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span><strong>Dev Mode:</strong> Backend is offline — showing mock data.</span>
      </div>
      <button @click="handleRetry" class="text-xs font-bold underline hover:text-amber-900">
        Retry
      </button>
    </div>

    <!-- Error Banner (Real API Error) -->
    <div 
      v-if="examStore.error" 
      class="bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl p-4 text-xs sm:text-sm font-medium flex items-center justify-between gap-3 shadow-2xs"
    >
      <div class="flex items-center gap-2.5">
        <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span>{{ examStore.error }}</span>
      </div>
      <button 
        @click="handleRetry" 
        class="px-3 py-1.5 bg-rose-100 hover:bg-rose-200 text-rose-800 rounded-lg text-xs font-bold transition-colors"
      >
        Retry
      </button>
    </div>

    <!-- Summary KPI Cards -->
    <ExamStatCards />

    <!-- Main Table Area -->
    <ExamTable ref="tableRef" />

    <!-- Bottom Section: Calendar, Distribution Chart & Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 lg:gap-6 pt-2">
      <ExamCalendar />
      <ExamOverviewChart />
      <ExamQuickActions />
    </div>

  </div>
</template>
