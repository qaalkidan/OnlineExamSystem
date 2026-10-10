<script setup lang="ts">
import { computed } from 'vue'
import { useInstructorResultStore } from '../../store/instructorResultStore'

const resultStore = useInstructorResultStore()

const percentage = computed(() => resultStore.gradingProgress?.percentage || 72)
const graded = computed(() => resultStore.gradingProgress?.graded || 90)
const total = computed(() => resultStore.gradingProgress?.total || 125)
const pending = computed(() => Math.max(0, total.value - graded.value))

const strokeDashArray = computed(() => {
  const p = Math.min(100, Math.max(0, percentage.value)) / 100
  const circumference = 2 * Math.PI * 38 // radius = 38 => ~238.76
  const len = Math.round(p * circumference * 10) / 10
  return `${len} ${circumference}`
})
</script>

<template>
  <div class="bg-white border border-[#E6EBF3] rounded-2xl p-5 shadow-xs hover:shadow-md transition-shadow duration-200 flex flex-col justify-between">
    <!-- Header -->
    <div class="flex items-center justify-between pb-3 mb-3 border-b border-[#E6EBF3]">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-xl bg-indigo-50 border border-indigo-100/60 text-[#4F35F3] flex items-center justify-center shadow-2xs">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-[#17243A]">Grading Progress</h3>
          <p class="text-[11px] text-[#71819B] font-medium">Cohort grading completion</p>
        </div>
      </div>
      <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-indigo-50 text-[#4F35F3] border border-indigo-100">
        {{ percentage }}%
      </span>
    </div>
    
    <!-- Visual Donut Chart -->
    <div class="flex flex-col items-center justify-center my-auto py-2">
      <div class="relative w-36 h-36 flex items-center justify-center">
        <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
          <!-- Background track -->
          <circle
            cx="50"
            cy="50"
            r="38"
            fill="none"
            stroke="#f1f5f9"
            stroke-width="10"
          />
          <!-- Progress stroke -->
          <circle
            cx="50"
            cy="50"
            r="38"
            fill="none"
            stroke="#4F35F3"
            stroke-width="10"
            stroke-linecap="round"
            :stroke-dasharray="strokeDashArray"
            stroke-dashoffset="0"
            class="transition-all duration-700 ease-out"
          />
        </svg>
        
        <!-- Center Text -->
        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
          <span class="text-2xl font-black text-[#17243A] leading-none">{{ percentage }}%</span>
          <span class="text-[10px] font-bold text-[#71819B] uppercase tracking-wider mt-1">Evaluated</span>
        </div>
      </div>
    </div>

    <!-- Breakdown Legend -->
    <div class="grid grid-cols-2 gap-2 pt-3 border-t border-[#E6EBF3] text-center text-xs">
      <div class="bg-emerald-50/60 border border-emerald-100/80 rounded-xl p-2">
        <span class="block text-[10px] font-bold text-emerald-700 uppercase">Graded</span>
        <span class="block font-black text-emerald-800 text-sm mt-0.5">{{ graded }}</span>
      </div>
      <div class="bg-amber-50/60 border border-amber-100/80 rounded-xl p-2">
        <span class="block text-[10px] font-bold text-amber-700 uppercase">Pending</span>
        <span class="block font-black text-amber-800 text-sm mt-0.5">{{ pending }}</span>
      </div>
    </div>

  </div>
</template>
