<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import type { UpcomingExam } from '../types'

const props = defineProps<{
  exams: UpcomingExam[]
}>()

const emit = defineEmits<{
  (e: 'start-exam', examId: number): void
  (e: 'view-details', exam: UpcomingExam): void
}>()

const router = useRouter()

// Limit to 3 exams matching screenshot
const displayedExams = computed(() => {
  return props.exams.slice(0, 3)
})

// Helper to format date into Month and Day for the calendar box
const formatMonth = (dateString?: string | null) => {
  if (!dateString) return 'UPC'
  const d = new Date(dateString)
  if (!isNaN(d.getTime())) {
    return d.toLocaleString('en-US', { month: 'short' }).toUpperCase()
  }
  const parts = dateString.split(' ')
  return parts.length > 0 ? parts[0].substring(0, 3).toUpperCase() : 'UPC'
}

const formatDay = (dateString?: string | null) => {
  if (!dateString) return '--'
  const d = new Date(dateString)
  if (!isNaN(d.getTime())) {
    return String(d.getDate()).padStart(2, '0')
  }
  const parts = dateString.split(' ')
  return parts.length > 1 ? parts[1].replace(',', '') : '01'
}

const formatTimeRange = (exam: UpcomingExam) => {
  const time = exam.startTime || '08:00 AM'
  const duration = exam.durationMinutes ? `${exam.durationMinutes}m` : '60m'
  return `${time} • ${duration}`
}
</script>

<template>
  <div class="bg-white rounded-2xl border border-[#E6EBF3] p-5 sm:p-6 shadow-2xs hover:border-slate-300 transition-all h-full flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between mb-5">
        <h3 class="text-base font-bold text-[#17243A]">Upcoming Exams</h3>
        <button
          @click="router.push('/student/exams')"
          class="text-[11px] font-bold text-[#4F35F3] hover:underline transition-colors cursor-pointer"
        >
          View Full Schedule
        </button>
      </div>

      <div class="space-y-4">
        <div
          v-for="exam in displayedExams"
          :key="exam.id"
          class="flex items-start gap-3.5 p-2 rounded-xl hover:bg-[#F6F8FC]/60 transition-colors"
        >
          <!-- Date Box matching screenshot -->
          <div class="flex flex-col items-center justify-center w-12 h-12 rounded-xl bg-[#F6F8FC] border border-[#E6EBF3] flex-shrink-0 shadow-2xs">
            <span class="text-[9px] font-black text-[#71819B] uppercase tracking-wider">{{ formatMonth(exam.scheduledDate) }}</span>
            <span class="text-sm font-black text-[#17243A] leading-none mt-0.5">{{ formatDay(exam.scheduledDate) }}</span>
          </div>

          <!-- Details -->
          <div class="flex-1 min-w-0">
            <h4 class="text-[13px] font-bold text-[#17243A] truncate">{{ exam.examType || exam.courseName }}</h4>
            <p class="text-[11px] text-[#71819B] truncate mt-0.5 font-medium">{{ exam.courseCode }} - {{ exam.courseName }}</p>
            <div class="flex items-center gap-3 mt-1.5 text-[10px] text-[#71819B] font-medium">
              <div class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>{{ formatTimeRange(exam) }}</span>
              </div>
              <div class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                </svg>
                <span>Online Portal</span>
              </div>
            </div>
          </div>

          <!-- Status / Action matching screenshot -->
          <div class="flex-shrink-0 pt-1">
            <button
              v-if="exam.status === 'Ready' || exam.attemptStatus === 'in_progress'"
              @click="emit('start-exam', exam.id)"
              class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200 hover:bg-emerald-100 transition-colors cursor-pointer shadow-2xs"
            >
              {{ exam.attemptStatus === 'in_progress' ? 'Continue Exam' : 'Ready to Start' }}
            </button>
            <span
              v-else
              class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2.5 py-1 rounded-md border border-amber-200/60 shadow-2xs"
            >
              Upcoming
            </span>
          </div>
        </div>

        <div v-if="exams.length === 0" class="text-center py-8">
          <div class="w-10 h-10 rounded-xl bg-slate-50 text-slate-400 mx-auto flex items-center justify-center mb-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
          </div>
          <p class="text-xs text-[#71819B] font-medium">No upcoming exams scheduled.</p>
        </div>
      </div>
    </div>
  </div>
</template>
