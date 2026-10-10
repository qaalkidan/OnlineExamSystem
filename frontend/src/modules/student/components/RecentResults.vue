<script setup lang="ts">
import { useRouter } from 'vue-router'
import type { RecentResult } from '../types'

const props = defineProps<{
  results: RecentResult[]
}>()

const emit = defineEmits<{
  (e: 'view-result', result: RecentResult): void
  (e: 'download-transcript'): void
}>()

const router = useRouter()
</script>

<template>
  <div class="bg-white rounded-2xl border border-[#E6EBF3] p-5 sm:p-6 shadow-2xs hover:border-slate-300 transition-all h-full flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between mb-5">
        <h3 class="text-base font-bold text-[#17243A]">Latest Results</h3>
        <button 
          @click="router.push('/student/results')"
          class="text-[11px] font-bold text-[#4F35F3] hover:underline transition-colors cursor-pointer"
        >
          View all
        </button>
      </div>

      <div class="space-y-3.5 flex-1 overflow-y-auto">
        <div
          v-for="result in results.slice(0, 4)"
          :key="result.id"
          class="flex items-center justify-between p-3.5 rounded-xl border border-transparent hover:border-[#E6EBF3] hover:bg-[#F6F8FC]/60 transition-all cursor-pointer group"
          @click="emit('view-result', result)"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
            </div>
            <div class="min-w-0">
              <h4 class="text-[13px] font-bold text-[#17243A] group-hover:text-[#4F35F3] transition-colors truncate">{{ result.courseName }}</h4>
              <p class="text-[11px] text-[#71819B] truncate mt-0.5 font-medium">{{ result.courseCode }} - {{ result.examTitle }}</p>
            </div>
          </div>
          
          <div class="text-right shrink-0 pl-2">
            <div class="flex items-baseline justify-end gap-1">
              <span class="text-[14px] font-black text-[#17243A]">{{ result.score }}</span>
              <span class="text-[10px] font-bold text-[#71819B]">/ {{ result.totalMarks }}</span>
            </div>
            <span 
              :class="[
                'text-[10px] font-bold px-2 py-0.5 rounded-md inline-block mt-1',
                result.percentage >= 50 
                  ? 'text-emerald-700 bg-emerald-50 border border-emerald-200/60' 
                  : 'text-rose-700 bg-rose-50 border border-rose-200/60'
              ]"
            >
              {{ result.percentage >= 50 ? 'Passed' : 'Failed' }}
            </span>
          </div>
        </div>

        <!-- Clean empty state matching screenshot -->
        <div v-if="results.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
          <p class="text-xs text-[#71819B] font-medium">No recent results found.</p>
        </div>
      </div>
    </div>
  </div>
</template>
