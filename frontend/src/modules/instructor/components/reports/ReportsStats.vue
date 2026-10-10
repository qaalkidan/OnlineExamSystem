<script setup lang="ts">
import { useInstructorReportStore } from '../../store/instructorReportStore'

const reportStore = useInstructorReportStore()
</script>

<template>
  <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3.5 sm:gap-4">
    
    <!-- Total Exams -->
    <div class="bg-white border border-[#E6EBF3] rounded-2xl p-4 sm:p-4.5 shadow-2xs hover:shadow-xs hover:border-slate-300 transition-all flex items-center gap-3.5">
      <div class="w-11 h-11 rounded-xl bg-[#EEF0FF] flex items-center justify-center text-[#4F35F3] shrink-0 shadow-2xs">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      </div>
      <div class="min-w-0">
        <span class="block text-[11px] font-bold text-[#71819B] mb-0.5 truncate">Total Exams</span>
        <div v-if="reportStore.isLoading" class="h-6 w-12 bg-slate-100 animate-pulse rounded my-0.5"></div>
        <span v-else class="block text-2xl font-black text-[#17243A] leading-tight tracking-tight">
          {{ reportStore.stats.total_exams }}
        </span>
        <div class="flex items-center gap-1 mt-0.5">
          <svg class="w-3 h-3 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18" />
          </svg>
          <span class="text-[10px] font-bold text-emerald-600">12.5%</span>
          <span class="text-[9px] text-[#71819B] font-medium whitespace-nowrap hidden sm:inline">vs Apr 01 - Apr 30</span>
        </div>
      </div>
    </div>

    <!-- Total Students -->
    <div class="bg-white border border-[#E6EBF3] rounded-2xl p-4 sm:p-4.5 shadow-2xs hover:shadow-xs hover:border-slate-300 transition-all flex items-center gap-3.5">
      <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0 shadow-2xs">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
      </div>
      <div class="min-w-0">
        <span class="block text-[11px] font-bold text-[#71819B] mb-0.5 truncate">Total Students</span>
        <div v-if="reportStore.isLoading" class="h-6 w-12 bg-slate-100 animate-pulse rounded my-0.5"></div>
        <span v-else class="block text-2xl font-black text-[#17243A] leading-tight tracking-tight">
          {{ reportStore.stats.total_students }}
        </span>
        <div class="flex items-center gap-1 mt-0.5">
          <svg class="w-3 h-3 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18" />
          </svg>
          <span class="text-[10px] font-bold text-emerald-600">8.3%</span>
          <span class="text-[9px] text-[#71819B] font-medium whitespace-nowrap hidden sm:inline">vs Apr 01 - Apr 30</span>
        </div>
      </div>
    </div>

    <!-- Average Score -->
    <div class="bg-white border border-[#E6EBF3] rounded-2xl p-4 sm:p-4.5 shadow-2xs hover:shadow-xs hover:border-slate-300 transition-all flex items-center gap-3.5">
      <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 shrink-0 shadow-2xs">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div class="min-w-0">
        <span class="block text-[11px] font-bold text-[#71819B] mb-0.5 truncate">Average Score</span>
        <div v-if="reportStore.isLoading" class="h-6 w-12 bg-slate-100 animate-pulse rounded my-0.5"></div>
        <span v-else class="block text-2xl font-black text-[#17243A] leading-tight tracking-tight">
          {{ reportStore.stats.average_score }}%
        </span>
        <div class="flex items-center gap-1 mt-0.5">
          <svg class="w-3 h-3 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18" />
          </svg>
          <span class="text-[10px] font-bold text-emerald-600">5.7%</span>
          <span class="text-[9px] text-[#71819B] font-medium whitespace-nowrap hidden sm:inline">vs Apr 01 - Apr 30</span>
        </div>
      </div>
    </div>

    <!-- Pass Rate -->
    <div class="bg-white border border-[#E6EBF3] rounded-2xl p-4 sm:p-4.5 shadow-2xs hover:shadow-xs hover:border-slate-300 transition-all flex items-center gap-3.5">
      <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600 shrink-0 shadow-2xs">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
      </div>
      <div class="min-w-0">
        <span class="block text-[11px] font-bold text-[#71819B] mb-0.5 truncate">Pass Rate</span>
        <div v-if="reportStore.isLoading" class="h-6 w-12 bg-slate-100 animate-pulse rounded my-0.5"></div>
        <span v-else class="block text-2xl font-black text-[#17243A] leading-tight tracking-tight">
          {{ reportStore.stats.pass_rate }}%
        </span>
        <div class="flex items-center gap-1 mt-0.5">
          <svg class="w-3 h-3 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18" />
          </svg>
          <span class="text-[10px] font-bold text-emerald-600">6.4%</span>
          <span class="text-[9px] text-[#71819B] font-medium whitespace-nowrap hidden sm:inline">vs Apr 01 - Apr 30</span>
        </div>
      </div>
    </div>

    <!-- Fail Rate -->
    <div class="bg-white border border-[#E6EBF3] rounded-2xl p-4 sm:p-4.5 shadow-2xs hover:shadow-xs hover:border-slate-300 transition-all flex items-center gap-3.5">
      <div class="w-11 h-11 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600 shrink-0 shadow-2xs">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div class="min-w-0">
        <span class="block text-[11px] font-bold text-[#71819B] mb-0.5 truncate">Fail Rate</span>
        <div v-if="reportStore.isLoading" class="h-6 w-12 bg-slate-100 animate-pulse rounded my-0.5"></div>
        <span v-else class="block text-2xl font-black text-[#17243A] leading-tight tracking-tight">
          {{ reportStore.stats.fail_rate }}%
        </span>
        <div class="flex items-center gap-1 mt-0.5">
          <svg class="w-3 h-3 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
          </svg>
          <span class="text-[10px] font-bold text-rose-600">-6.4%</span>
          <span class="text-[9px] text-[#71819B] font-medium whitespace-nowrap hidden sm:inline">vs Apr 01 - Apr 30</span>
        </div>
      </div>
    </div>

  </div>
</template>
