<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  exam: any | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'start-exam', examId: number): void
  (e: 'continue-exam', examId: number): void
}>()

const isAvailableNow = computed(() => {
  if (!props.exam) return false
  const status = (props.exam.status || '').toLowerCase()
  return status === 'available now' || status === 'ready'
})

const isInProgress = computed(() => {
  if (!props.exam) return false
  const status = (props.exam.attemptStatus || props.exam.attempt_status || props.exam.status || '').toLowerCase()
  return status === 'in_progress' || status === 'in progress'
})
</script>

<template>
  <div
    v-if="exam"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 overflow-y-auto animate-in fade-in duration-200"
    @click.self="emit('close')"
  >
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl space-y-5 animate-in zoom-in-95 duration-200 my-8">
      
      <!-- Modal Header -->
      <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
        <div>
          <div class="flex items-center gap-2 mb-1.5 flex-wrap">
            <span class="px-2.5 py-0.5 rounded-md font-mono font-black text-xs uppercase bg-indigo-50 text-[#5138ed] border border-indigo-200/80">
              {{ exam.courseCode || exam.course_code || 'EXAM' }}
            </span>
            <span class="px-2 py-0.5 rounded-md text-xs font-bold bg-slate-100 text-slate-700">
              {{ exam.examType || exam.exam_type || 'Exam' }}
            </span>
            <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold text-slate-500 bg-slate-50 border border-slate-100">
              {{ exam.section || 'All Sections' }}
            </span>
          </div>
          <h3 class="text-lg font-black text-[#17243A] leading-tight">
            {{ exam.title || exam.examTitle }}
          </h3>
          <p class="text-xs text-slate-500 mt-0.5 font-medium">
            {{ exam.courseName || exam.course_name }}
          </p>
        </div>

        <button
          @click="emit('close')"
          class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition cursor-pointer"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>

      <!-- Schedule & Venue Snapshot -->
      <div class="grid grid-cols-2 gap-2 text-xs bg-[#F6F8FC] p-3.5 rounded-2xl border border-[#E6EBF3]">
        <div>
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Examination Date</span>
          <span class="font-bold text-slate-800 text-xs mt-0.5 block">{{ exam.date || 'Scheduled Date' }}</span>
        </div>
        <div>
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Time Window</span>
          <span class="font-bold text-slate-800 text-xs mt-0.5 block">{{ exam.timeRange || (exam.startTime + ' - ' + exam.endTime) }}</span>
        </div>
        <div class="pt-2 border-t border-slate-200/60">
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Duration & Marks</span>
          <span class="font-bold text-slate-800 text-xs mt-0.5 block">{{ exam.duration || (exam.durationMinutes + 'm') }} • {{ exam.totalMarks || 100 }} Marks</span>
        </div>
        <div class="pt-2 border-t border-slate-200/60">
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Venue / Room</span>
          <span class="font-bold text-slate-800 text-xs mt-0.5 block">{{ exam.room || 'Online Portal' }}</span>
        </div>
      </div>

      <!-- Assigned Examiner -->
      <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 border border-slate-100 text-xs">
        <div class="w-9 h-9 rounded-xl bg-indigo-50 text-[#5138ed] font-bold flex items-center justify-center shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
        </div>
        <div class="min-w-0 flex-1">
          <p class="font-bold text-slate-900 truncate">{{ exam.instructor || exam.instructor_name || 'Department Examiner' }}</p>
          <p class="text-[11px] text-slate-500 truncate">{{ exam.instructorEmail || 'Course Instructor' }}</p>
        </div>
      </div>

      <!-- Examination Rules & Security -->
      <div class="space-y-2 text-xs">
        <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wider">Candidate Instructions & Rules</h4>
        <div class="p-3.5 rounded-2xl bg-amber-50/70 border border-amber-200/80 text-amber-900 text-xs space-y-1.5 leading-relaxed">
          <p class="font-medium" v-if="exam.instructions">
            {{ exam.instructions }}
          </p>
          <p class="font-medium" v-else>
            Please ensure you have a stable network connection before starting. The exam timer starts immediately upon clicking Start Exam.
          </p>
          <div class="pt-1.5 border-t border-amber-200/60 flex flex-wrap items-center gap-3 text-[11px] font-bold text-amber-800">
            <span>• {{ exam.allowCalculator ? 'Scientific Calculator Allowed' : 'No Calculator Permitted' }}</span>
            <span>• Auto-submit on expiration</span>
            <span>• Single device session</span>
          </div>
        </div>
      </div>

      <!-- Modal Footer Actions -->
      <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-100">
        <button
          @click="emit('close')"
          class="px-4 py-2.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition cursor-pointer"
        >
          Close
        </button>

        <button
          v-if="isInProgress"
          @click="emit('continue-exam', exam.id)"
          class="flex items-center gap-1.5 px-5 py-2.5 text-xs font-bold text-white bg-[#4F35F3] hover:bg-indigo-700 rounded-xl shadow-sm transition cursor-pointer"
        >
          <span>Continue Exam</span>
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
        </button>

        <button
          v-else-if="isAvailableNow"
          @click="emit('start-exam', exam.id)"
          class="flex items-center gap-1.5 px-5 py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-sm transition cursor-pointer"
        >
          <span>Start Exam Now</span>
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
        </button>
      </div>

    </div>
  </div>
</template>
