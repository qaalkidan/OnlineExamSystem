<script setup lang="ts">
import { useInstructorResultStore } from '../../store/instructorResultStore'

const resultStore = useInstructorResultStore()
</script>

<template>
  <div class="mb-6">
    <!-- 4 Stat Cards in Responsive Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4">
      
      <!-- 1. Completed Exams -->
      <div class="bg-white border border-[#E6EBF3] rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-all duration-200 flex items-center gap-3.5 group">
        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-emerald-50 border border-emerald-100/80 flex items-center justify-center text-[#10B981] shrink-0 group-hover:scale-105 transition-transform">
          <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-[10px] font-bold text-[#71819B] uppercase tracking-wider truncate">Completed Exams</span>
          <div v-if="resultStore.isLoading" class="h-6 w-12 bg-slate-100 animate-pulse rounded my-1"></div>
          <span v-else class="text-xl sm:text-2xl font-black text-[#17243A] leading-tight my-0.5">
            {{ resultStore.stats.completed_exams }}
          </span>
          <span class="text-[11px] font-medium text-emerald-600 truncate">Exams completed</span>
        </div>
      </div>

      <!-- 2. Pending Grading -->
      <div class="bg-white border border-[#E6EBF3] rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-all duration-200 flex items-center gap-3.5 group">
        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-amber-50 border border-amber-100/80 flex items-center justify-center text-[#F59E0B] shrink-0 group-hover:scale-105 transition-transform">
          <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-[10px] font-bold text-[#71819B] uppercase tracking-wider truncate">Pending Grading</span>
          <div v-if="resultStore.isLoading" class="h-6 w-12 bg-slate-100 animate-pulse rounded my-1"></div>
          <span v-else class="text-xl sm:text-2xl font-black text-[#17243A] leading-tight my-0.5">
            {{ resultStore.stats.pending_manual_grading }}
          </span>
          <span class="text-[11px] font-medium text-amber-600 truncate">Exams awaiting grading</span>
        </div>
      </div>

      <!-- 3. Results Published -->
      <div class="bg-white border border-[#E6EBF3] rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-all duration-200 flex items-center gap-3.5 group">
        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-indigo-50 border border-indigo-100/80 flex items-center justify-center text-[#4F35F3] shrink-0 group-hover:scale-105 transition-transform">
          <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-[10px] font-bold text-[#71819B] uppercase tracking-wider truncate">Results Published</span>
          <div v-if="resultStore.isLoading" class="h-6 w-12 bg-slate-100 animate-pulse rounded my-1"></div>
          <span v-else class="text-xl sm:text-2xl font-black text-[#17243A] leading-tight my-0.5">
            {{ resultStore.stats.published_results }}
          </span>
          <span class="text-[11px] font-medium text-[#4F35F3] truncate">Visible to students</span>
        </div>
      </div>

      <!-- 4. Average Score -->
      <div class="bg-white border border-[#E6EBF3] rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-all duration-200 flex items-center gap-3.5 group">
        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-sky-50 border border-sky-100/80 flex items-center justify-center text-[#0EA5E9] shrink-0 group-hover:scale-105 transition-transform">
          <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
          </svg>
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-[10px] font-bold text-[#71819B] uppercase tracking-wider truncate">Average Score</span>
          <div v-if="resultStore.isLoading" class="h-6 w-12 bg-slate-100 animate-pulse rounded my-1"></div>
          <span v-else class="text-xl sm:text-2xl font-black text-[#17243A] leading-tight my-0.5">
            {{ resultStore.stats.average_score }}%
          </span>
          <span class="text-[11px] font-medium text-slate-500 truncate">Across all course exams</span>
        </div>
      </div>

    </div>
  </div>
</template>
