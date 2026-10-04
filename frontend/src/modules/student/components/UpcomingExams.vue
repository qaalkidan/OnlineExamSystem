<script setup lang="ts">
import { computed } from 'vue'
import type { UpcomingExam } from '../types'

const props = defineProps<{
  exams: UpcomingExam[]
}>()

const emit = defineEmits<{
  (e: 'start-exam', examId: number): void
  (e: 'view-details', exam: UpcomingExam): void
}>()

// Limit to 3 exams
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
</script>

<template>
  <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm h-full">
    <div class="flex items-center justify-between mb-6">
      <h3 class="text-base font-bold text-slate-900">Upcoming Exams</h3>
      <button class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700 transition-colors">
        View Full Schedule
      </button>
    </div>

    <div class="space-y-6">
      <div
        v-for="exam in displayedExams"
        :key="exam.id"
        class="flex items-start gap-4"
      >
        <!-- Date Box -->
        <div class="flex flex-col items-center justify-center w-12 h-12 rounded-xl bg-slate-50 border border-slate-100 flex-shrink-0">
          <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">{{ formatMonth(exam.scheduledDate) }}</span>
          <span class="text-sm font-black text-slate-800 leading-none mt-0.5">{{ formatDay(exam.scheduledDate) }}</span>
        </div>

        <!-- Details -->
        <div class="flex-1 min-w-0">
          <h4 class="text-[13px] font-bold text-slate-900 truncate">{{ exam.courseName }}</h4>
          <p class="text-[11px] text-slate-500 truncate mt-0.5">{{ exam.courseCode }} - {{ exam.examType }}</p>
          <div class="flex items-center gap-3 mt-1.5 text-[10px] text-slate-400 font-medium">
            <div class="flex items-center gap-1">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              {{ exam.startTime }} - {{ exam.durationMinutes }}m
            </div>
            <div class="flex items-center gap-1">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
              Online Portal
            </div>
          </div>
        </div>

        <!-- Status / Action -->
        <div class="flex-shrink-0 pt-1">
          <button
            v-if="exam.status === 'Ready'"
            @click="emit('start-exam', exam.id)"
            class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-100 hover:bg-emerald-100 transition-colors"
          >
            Ready to Start
          </button>
          <span
            v-else
            class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2.5 py-1 rounded-md border border-amber-100"
          >
            Upcoming
          </span>
        </div>
      </div>

      <div v-if="exams.length === 0" class="text-center py-6">
        <p class="text-xs text-slate-500">No upcoming exams scheduled.</p>
      </div>
    </div>
  </div>
</template>
