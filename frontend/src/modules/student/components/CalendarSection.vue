<script setup lang="ts">
import { ref, computed } from 'vue'
import type { CalendarEvent } from '../types'

const props = defineProps<{
  events: CalendarEvent[]
}>()

const currentMonthIndex = ref(5) // 0 = Jan, 5 = June
const currentYear = ref(2026)

const months = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
]

const currentMonthName = computed(() => months[currentMonthIndex.value])

const prevMonth = () => {
  if (currentMonthIndex.value === 0) {
    currentMonthIndex.value = 11
    currentYear.value--
  } else {
    currentMonthIndex.value--
  }
}

const nextMonth = () => {
  if (currentMonthIndex.value === 11) {
    currentMonthIndex.value = 0
    currentYear.value++
  } else {
    currentMonthIndex.value++
  }
}

const daysInMonth = computed(() => {
  const days = new Date(currentYear.value, currentMonthIndex.value + 1, 0).getDate()
  return Array.from({ length: days }, (_, i) => i + 1)
})

const startDayOfWeek = computed(() => {
  return new Date(currentYear.value, currentMonthIndex.value, 1).getDay()
})

const weekDays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']

const getEventForDay = (day: number) => {
  const m = String(currentMonthIndex.value + 1).padStart(2, '0')
  const d = String(day).padStart(2, '0')
  const formattedDate = `${currentYear.value}-${m}-${d}`
  return props.events.find(e => e.date === formattedDate)
}

const getDayBadgeClass = (event: CalendarEvent | undefined) => {
  if (!event) return 'text-[#17243A] hover:bg-[#F6F8FC] font-semibold'
  switch (event.type) {
    case 'Exam':
      return 'bg-[#4F35F3] text-white font-bold ring-2 ring-[#EEF0FF] shadow-2xs'
    case 'Deadline':
      return 'bg-rose-500 text-white font-bold ring-2 ring-rose-100 shadow-2xs'
    case 'Holiday':
      return 'bg-emerald-500 text-white font-bold ring-2 ring-emerald-100 shadow-2xs'
    default:
      return 'bg-[#3295FF] text-white font-bold ring-2 ring-blue-100 shadow-2xs'
  }
}
</script>

<template>
  <div class="bg-white rounded-2xl border border-[#E6EBF3] p-5 sm:p-6 shadow-2xs hover:border-slate-300 transition-all h-full flex flex-col justify-between">
    <div>
      <!-- Header matching screenshot -->
      <div class="flex items-center justify-between mb-5">
        <h3 class="text-base font-bold text-[#17243A]">Academic Calendar</h3>
        <div class="flex items-center gap-3">
          <button @click="prevMonth" class="w-7 h-7 rounded-lg flex items-center justify-center text-[#71819B] hover:text-[#17243A] hover:bg-[#F6F8FC] transition-colors cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
            </svg>
          </button>
          <span class="text-[13px] font-black text-[#17243A] min-w-[85px] text-center">{{ currentMonthName }} {{ currentYear }}</span>
          <button @click="nextMonth" class="w-7 h-7 rounded-lg flex items-center justify-center text-[#71819B] hover:text-[#17243A] hover:bg-[#F6F8FC] transition-colors cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
            </svg>
          </button>
        </div>
      </div>

      <!-- Calendar Grid -->
      <div class="mb-4">
        <!-- Weekday header -->
        <div class="grid grid-cols-7 gap-1 mb-2">
          <div v-for="day in weekDays" :key="day" class="text-center text-[10px] font-black text-[#71819B] uppercase tracking-wider py-1">
            {{ day }}
          </div>
        </div>
        
        <!-- Days -->
        <div class="grid grid-cols-7 gap-y-2 gap-x-1">
          <!-- Offset empty cells -->
          <div v-for="offset in startDayOfWeek" :key="'offset-' + offset" class="text-center"></div>
          
          <!-- Month day numbers -->
          <div v-for="day in daysInMonth" :key="day" class="flex justify-center">
            <button 
              :title="getEventForDay(day)?.title"
              :class="['w-7 h-7 flex items-center justify-center rounded-full text-[11px] transition-all cursor-pointer', getDayBadgeClass(getEventForDay(day))]"
            >
              {{ day }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Legend matching screenshot -->
    <div class="pt-4 border-t border-slate-100 flex flex-wrap items-center gap-x-4 gap-y-2 text-[10px] font-bold text-[#71819B] justify-center">
      <div class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-[#4F35F3]"></span>
        <span>Exam</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
        <span>Deadline</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
        <span>Holiday</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-[#3295FF]"></span>
        <span>Event</span>
      </div>
    </div>
  </div>
</template>
