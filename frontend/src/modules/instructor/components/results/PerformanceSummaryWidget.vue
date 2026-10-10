<script setup lang="ts">
import { computed } from 'vue'
import { useInstructorResultStore } from '../../store/instructorResultStore'

const resultStore = useInstructorResultStore()

const totalExams = computed(() => Math.max(1, resultStore.results.length))
const publishedCount = computed(() => resultStore.results.filter(r => r.is_published || r.status === 'Published').length)
const pendingCount = computed(() => resultStore.results.filter(r => (r.status as string) === 'Pending' || (r.status as string) === 'Graded' || r.status === 'Pending Grading').length)
const draftCount = computed(() => resultStore.results.filter(r => r.status === 'Draft').length)

const publishedPct = computed(() => Math.round((publishedCount.value / totalExams.value) * 100))
const pendingPct = computed(() => Math.round((pendingCount.value / totalExams.value) * 100))
const draftPct = computed(() => Math.max(0, 100 - publishedPct.value - pendingPct.value))
</script>

<template>
  <div class="bg-white border border-[#E6EBF3] rounded-2xl shadow-xs overflow-hidden flex flex-col justify-between hover:shadow-md transition-shadow duration-200">
    
    <!-- Top Section: Pending Manual Grading -->
    <div class="p-5 border-b border-[#E6EBF3]">
      <div class="flex items-center justify-between mb-3.5">
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-lg bg-amber-50 text-[#F59E0B] flex items-center justify-center shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h2 class="text-xs sm:text-sm font-bold text-[#17243A]">Pending Manual Grading</h2>
        </div>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200/60">
          {{ resultStore.stats.pending_manual_grading }} Tasks
        </span>
      </div>

      <div class="space-y-3">
        <!-- Essay Questions -->
        <div class="flex items-start justify-between p-2.5 rounded-xl bg-slate-50/70 border border-slate-100">
          <div class="flex items-start gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-orange-50 border border-orange-100 flex items-center justify-center text-orange-500 mt-0.5 shrink-0">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
            </div>
            <div class="flex flex-col">
              <span class="text-xs font-bold text-[#17243A]">Essay & Free Response</span>
              <span class="text-[11px] text-[#71819B] mt-0.5">Short answers & essays</span>
            </div>
          </div>
          <span class="px-2 py-0.5 text-[9px] font-bold bg-rose-50 text-rose-600 border border-rose-100 rounded-md">
            Priority
          </span>
        </div>

        <!-- Automated Grading Check -->
        <div class="flex items-start justify-between p-2.5 rounded-xl bg-slate-50/70 border border-slate-100">
          <div class="flex items-start gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-500 mt-0.5 shrink-0">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div class="flex flex-col">
              <span class="text-xs font-bold text-[#17243A]">Multiple Choice & True/False</span>
              <span class="text-[11px] text-[#71819B] mt-0.5">Auto-graded on submission</span>
            </div>
          </div>
          <span class="px-2 py-0.5 text-[9px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100 rounded-md">
            Automated
          </span>
        </div>
      </div>
    </div>

    <!-- Bottom Section: Result Status Overview -->
    <div class="p-5">
      <h2 class="text-xs sm:text-sm font-bold text-[#17243A] mb-3">Result Status Overview</h2>
      
      <div class="space-y-3">
        <!-- Published -->
        <div>
          <div class="flex items-center justify-between mb-1 text-xs">
            <div class="flex items-center gap-2">
              <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
              <span class="font-semibold text-slate-700">Published</span>
            </div>
            <span class="font-bold text-slate-800">{{ publishedCount }} <span class="text-[10px] text-slate-400 font-normal">({{ publishedPct }}%)</span></span>
          </div>
          <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden flex">
            <div class="h-full bg-emerald-500 rounded-full transition-all duration-500" :style="`width: ${publishedPct}%`"></div>
          </div>
        </div>
        
        <!-- Pending -->
        <div>
          <div class="flex items-center justify-between mb-1 text-xs">
            <div class="flex items-center gap-2">
              <div class="w-2 h-2 rounded-full bg-amber-500"></div>
              <span class="font-semibold text-slate-700">Pending Evaluation</span>
            </div>
            <span class="font-bold text-slate-800">{{ pendingCount }} <span class="text-[10px] text-slate-400 font-normal">({{ pendingPct }}%)</span></span>
          </div>
          <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden flex">
            <div class="h-full bg-amber-500 rounded-full transition-all duration-500" :style="`width: ${pendingPct}%`"></div>
          </div>
        </div>
        
        <!-- Draft -->
        <div>
          <div class="flex items-center justify-between mb-1 text-xs">
            <div class="flex items-center gap-2">
              <div class="w-2 h-2 rounded-full bg-slate-400"></div>
              <span class="font-semibold text-slate-700">Draft / In Preparation</span>
            </div>
            <span class="font-bold text-slate-800">{{ draftCount }} <span class="text-[10px] text-slate-400 font-normal">({{ draftPct }}%)</span></span>
          </div>
          <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden flex">
            <div class="h-full bg-slate-300 rounded-full transition-all duration-500" :style="`width: ${draftPct}%`"></div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
