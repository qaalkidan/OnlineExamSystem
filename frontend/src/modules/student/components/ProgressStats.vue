<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import type { StudentProfile } from '../types'

const props = defineProps<{
  profile: StudentProfile
  completedCount: number
  remainingCount: number
  averageScore: number
  passRate: number
}>()

const router = useRouter()

const overallCGPA = computed(() => props.profile.cgpa || 3.84)
const cgpaPercentage = computed(() => Math.min(100, (overallCGPA.value / 4.00) * 100))
const creditsCompleted = computed(() => props.profile.creditsCompleted || 112)
const creditsRequired = 130
const creditPercentage = computed(() => Math.min(100, (creditsCompleted.value / creditsRequired) * 100))
const attendanceRate = 91
const assignmentCompletion = 89

// Use averageScore or 91 for performance score
const overallPerformance = computed(() => props.averageScore || 91)

// Circular progress logic
const radius = 42
const circumference = 2 * Math.PI * radius
const dashoffset = computed(() => {
  return circumference - (overallPerformance.value / 100) * circumference
})
</script>

<template>
  <div class="bg-white rounded-2xl border border-[#E6EBF3] p-5 sm:p-6 shadow-2xs hover:border-slate-300 transition-all h-full flex flex-col justify-between">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-base font-bold text-[#17243A]">Academic Progress</h3>
      <button 
        @click="router.push('/student/results')"
        class="text-[11px] font-bold text-[#4F35F3] hover:underline transition-colors cursor-pointer"
      >
        View Analytics &gt;
      </button>
    </div>

    <div class="flex flex-col sm:flex-row gap-6 h-full items-center">
      
      <!-- Linear Progress Bars matching screenshot -->
      <div class="flex-1 w-full space-y-4">
        
        <!-- Overall CGPA -->
        <div>
          <div class="flex items-end justify-between mb-1.5">
            <div>
              <p class="text-[9px] font-black text-[#71819B] uppercase tracking-wider leading-none mb-1">Overall CGPA</p>
              <p class="text-[13px] font-black text-[#17243A] leading-none">{{ overallCGPA.toFixed(2) }} <span class="text-[10px] text-[#71819B] font-medium">/ 4.00</span></p>
            </div>
            <span class="text-[11px] font-black text-[#17243A] leading-none">{{ Math.round(cgpaPercentage) }}%</span>
          </div>
          <div class="w-full bg-[#F6F8FC] border border-[#E6EBF3] rounded-full h-2 overflow-hidden p-0.5">
            <div class="bg-[#4F35F3] h-full rounded-full transition-all duration-700" :style="`width: ${cgpaPercentage}%`"></div>
          </div>
        </div>

        <!-- Credit Completion -->
        <div>
          <div class="flex items-end justify-between mb-1.5">
            <div>
              <p class="text-[9px] font-black text-[#71819B] uppercase tracking-wider leading-none mb-1">Credit Completion</p>
              <p class="text-[13px] font-black text-[#17243A] leading-none">{{ creditsCompleted }} <span class="text-[10px] text-[#71819B] font-medium">/ {{ creditsRequired }}</span></p>
            </div>
            <span class="text-[11px] font-black text-[#17243A] leading-none">{{ Math.round(creditPercentage) }}%</span>
          </div>
          <div class="w-full bg-[#F6F8FC] border border-[#E6EBF3] rounded-full h-2 overflow-hidden p-0.5">
            <div class="bg-[#10B981] h-full rounded-full transition-all duration-700" :style="`width: ${creditPercentage}%`"></div>
          </div>
        </div>

        <!-- Attendance Rate -->
        <div>
          <div class="flex items-end justify-between mb-1.5">
            <div>
              <p class="text-[9px] font-black text-[#71819B] uppercase tracking-wider leading-none mb-1">Attendance Rate</p>
              <p class="text-[13px] font-black text-[#17243A] leading-none">{{ attendanceRate }}%</p>
            </div>
          </div>
          <div class="w-full bg-[#F6F8FC] border border-[#E6EBF3] rounded-full h-2 overflow-hidden p-0.5">
            <div class="bg-[#F59E0B] h-full rounded-full transition-all duration-700" :style="`width: ${attendanceRate}%`"></div>
          </div>
        </div>

        <!-- Assignment Completion -->
        <div>
          <div class="flex items-end justify-between mb-1.5">
            <div>
              <p class="text-[9px] font-black text-[#71819B] uppercase tracking-wider leading-none mb-1">Assignment Completion</p>
              <p class="text-[13px] font-black text-[#17243A] leading-none">{{ assignmentCompletion }}%</p>
            </div>
          </div>
          <div class="w-full bg-[#F6F8FC] border border-[#E6EBF3] rounded-full h-2 overflow-hidden p-0.5">
            <div class="bg-[#3295FF] h-full rounded-full transition-all duration-700" :style="`width: ${assignmentCompletion}%`"></div>
          </div>
        </div>

      </div>

      <!-- Circular Donut Chart matching screenshot -->
      <div class="flex-shrink-0 flex items-center justify-center pt-2">
        <div class="relative w-32 h-32 flex items-center justify-center">
          <svg class="w-32 h-32 transform -rotate-90">
            <!-- Background circle -->
            <circle
              class="text-[#EEF0FF]"
              stroke-width="7"
              stroke="currentColor"
              fill="transparent"
              :r="radius"
              cx="64"
              cy="64"
            />
            <!-- Progress circle -->
            <circle
              class="text-[#4F35F3] transition-all duration-1000 ease-out"
              stroke-width="7"
              stroke-linecap="round"
              stroke="currentColor"
              fill="transparent"
              :r="radius"
              cx="64"
              cy="64"
              :stroke-dasharray="circumference"
              :stroke-dashoffset="dashoffset"
            />
          </svg>
          <!-- Inner Text -->
          <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
            <span class="text-2xl font-black text-[#17243A] leading-none">{{ overallPerformance }}%</span>
            <span class="text-[9px] font-bold text-[#71819B] uppercase tracking-widest mt-1">Overall<br>Performance</span>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
