<script setup lang="ts">
import { ref, computed } from 'vue'
import { useInstructorExamStore } from '../../store/instructorExamStore'

const examStore = useInstructorExamStore()

const currentDate = ref(new Date())

const currentYear = computed(() => currentDate.value.getFullYear())
const currentMonth = computed(() => currentDate.value.getMonth())

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
]

const daysOfWeek = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']

const monthTitle = computed(() => `${monthNames[currentMonth.value]} ${currentYear.value}`)

const prevMonth = () => {
  currentDate.value = new Date(currentYear.value, currentMonth.value - 1, 1)
}

const nextMonth = () => {
  currentDate.value = new Date(currentYear.value, currentMonth.value + 1, 1)
}

// Days calculation for calendar grid
const calendarDays = computed(() => {
  const year = currentYear.value
  const month = currentMonth.value

  const firstDayIndex = new Date(year, month, 1).getDay()
  const daysInCurrentMonth = new Date(year, month + 1, 0).getDate()
  const daysInPrevMonth = new Date(year, month, 0).getDate()

  const today = new Date()
  const isCurrentMonthActual = today.getFullYear() === year && today.getMonth() === month
  const actualDateToday = isCurrentMonthActual ? today.getDate() : -1

  // Collect scheduled exam dates in this month
  const examDatesInMonth = new Set<number>()
  examStore.exams.forEach(exam => {
    if (exam.scheduled_at) {
      const d = new Date(exam.scheduled_at)
      if (d.getFullYear() === year && d.getMonth() === month) {
        examDatesInMonth.add(d.getDate())
      }
    }
  })

  const days: Array<{
    date: number
    currentMonth: boolean
    isToday: boolean
    hasExam: boolean
  }> = []

  // Prev month filler
  for (let i = firstDayIndex - 1; i >= 0; i--) {
    days.push({
      date: daysInPrevMonth - i,
      currentMonth: false,
      isToday: false,
      hasExam: false,
    })
  }

  // Current month days
  for (let d = 1; d <= daysInCurrentMonth; d++) {
    days.push({
      date: d,
      currentMonth: true,
      isToday: d === actualDateToday,
      hasExam: examDatesInMonth.has(d),
    })
  }

  // Next month filler to complete 35 or 42 grid cells
  const remainingCells = 35 - days.length > 0 ? 35 - days.length : 42 - days.length
  for (let d = 1; d <= remainingCells; d++) {
    days.push({
      date: d,
      currentMonth: false,
      isToday: false,
      hasExam: false,
    })
  }

  return days
})
</script>

<template>
  <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-xs flex flex-col justify-between">
    <!-- Header -->
    <div class="flex items-center justify-between mb-3">
      <div class="flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-[#5138ed]">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Exam Schedule</h2>
      </div>

      <!-- Navigation Arrows -->
      <div class="flex items-center gap-1">
        <button 
          @click="prevMonth"
          class="p-1 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-700 transition-colors"
          aria-label="Previous month"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <span class="text-xs font-bold text-slate-700 px-1">{{ monthTitle }}</span>
        <button 
          @click="nextMonth"
          class="p-1 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-700 transition-colors"
          aria-label="Next month"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Days of Week -->
    <div class="grid grid-cols-7 gap-1 mb-1 text-center">
      <span v-for="day in daysOfWeek" :key="day" class="text-[10px] font-bold text-slate-400 uppercase">
        {{ day }}
      </span>
    </div>

    <!-- Calendar Grid -->
    <div class="grid grid-cols-7 gap-1">
      <div 
        v-for="(day, index) in calendarDays" 
        :key="index"
        class="h-7 rounded-lg flex flex-col items-center justify-center text-[11px] font-semibold relative transition-colors"
        :class="[
          !day.currentMonth ? 'text-slate-300' : 'text-slate-700 hover:bg-slate-50',
          day.isToday ? 'bg-[#5138ed] text-white font-bold shadow-2xs' : '',
          day.hasExam && !day.isToday ? 'bg-indigo-50/80 text-[#5138ed] font-bold border border-indigo-100/80' : ''
        ]"
      >
        <span>{{ day.date }}</span>
        <!-- Exam marker dot -->
        <span 
          v-if="day.hasExam && day.isToday" 
          class="absolute bottom-0.5 w-1 h-1 rounded-full bg-white"
        ></span>
        <span 
          v-else-if="day.hasExam" 
          class="absolute bottom-0.5 w-1 h-1 rounded-full bg-[#5138ed]"
        ></span>
      </div>
    </div>

    <!-- Footer Legend -->
    <div class="flex items-center justify-between text-[10px] text-slate-400 pt-2 border-t border-slate-100 mt-2">
      <div class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-[#5138ed]"></span>
        <span>Today</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-indigo-200 border border-indigo-300"></span>
        <span>Scheduled Exam</span>
      </div>
    </div>
  </div>
</template>
